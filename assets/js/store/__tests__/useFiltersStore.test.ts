import { describe, it, expect, beforeEach } from 'vitest';
import { useFiltersStore, legacyFiltersToGroup, selectLegacyFilters, isEmptyRoot, selectIsComplex } from '../useFiltersStore';
import type { FilterCondition, FilterGroup } from '@/types/api';

function getState() {
	return useFiltersStore.getState();
}

const emptyRoot: FilterGroup = { type: 'group', combinator: 'AND', children: [] };

function reset() {
	useFiltersStore.setState( {
		root: emptyRoot,
		searchQuery: '',
	} );
}

const condA: FilterCondition = { type: 'condition', field: 'name', operator: '=', value: 'Test' };
const condB: FilterCondition = { type: 'condition', field: 'sku', operator: 'LIKE', value: 'SKU-' };

describe( 'useFiltersStore', () => {
	beforeEach( () => {
		reset();
	} );

	it( 'starts with an empty AND root group', () => {
		const { root } = getState();
		expect( root.type ).toBe( 'group' );
		expect( root.combinator ).toBe( 'AND' );
		expect( root.children ).toHaveLength( 0 );
	} );

	describe( 'setRoot', () => {
		it( 'replaces the entire root group', () => {
			const newRoot: FilterGroup = {
				type: 'group',
				combinator: 'OR',
				children: [ condA ],
			};
			getState().setRoot( newRoot );
			expect( getState().root ).toEqual( newRoot );
		} );
	} );

	describe( 'addCondition', () => {
		it( 'appends to root when path is empty string', () => {
			getState().addCondition( '', condA );
			expect( getState().root.children ).toHaveLength( 1 );
			expect( getState().root.children[ 0 ] ).toEqual( condA );
		} );

		it( 'appends to root when path is empty', () => {
			getState().addCondition( '', condA );
			getState().addCondition( '', condB );
			expect( getState().root.children ).toHaveLength( 2 );
		} );

		it( 'appends condition to a nested group by path', () => {
			// Add a sub-group at root first.
			getState().addGroup( '', 'OR' );
			// Path '0' = first child of root.
			getState().addCondition( '0', condA );
			const nested = getState().root.children[ 0 ] as FilterGroup;
			expect( nested.children ).toHaveLength( 1 );
			expect( nested.children[ 0 ] ).toEqual( condA );
		} );
	} );

	describe( 'addGroup', () => {
		it( 'creates nested group at root', () => {
			getState().addGroup( '', 'OR' );
			expect( getState().root.children ).toHaveLength( 1 );
			const child = getState().root.children[ 0 ] as FilterGroup;
			expect( child.type ).toBe( 'group' );
			expect( child.combinator ).toBe( 'OR' );
			expect( child.children ).toHaveLength( 0 );
		} );
	} );

	describe( 'removeNode', () => {
		it( 'removes top-level condition at path "0"', () => {
			getState().addCondition( '', condA );
			getState().addCondition( '', condB );
			getState().removeNode( '0' );
			expect( getState().root.children ).toHaveLength( 1 );
			expect( getState().root.children[ 0 ] ).toEqual( condB );
		} );

		it( 'removes a nested condition', () => {
			getState().addGroup( '', 'AND' );
			getState().addCondition( '0', condA );
			getState().addCondition( '0', condB );
			getState().removeNode( '0.0' );
			const nested = getState().root.children[ 0 ] as FilterGroup;
			expect( nested.children ).toHaveLength( 1 );
			expect( nested.children[ 0 ] ).toEqual( condB );
		} );

		it( 'is a no-op for an invalid path', () => {
			getState().addCondition( '', condA );
			getState().removeNode( '99' );
			expect( getState().root.children ).toHaveLength( 1 );
		} );
	} );

	describe( 'updateCondition', () => {
		it( 'patches a top-level condition at path "0"', () => {
			getState().addCondition( '', condA );
			getState().updateCondition( '0', { value: 'Updated' } );
			const updated = getState().root.children[ 0 ] as FilterCondition;
			expect( updated.value ).toBe( 'Updated' );
			expect( updated.field ).toBe( condA.field );
		} );

		it( 'patches a nested condition', () => {
			getState().addGroup( '', 'AND' );
			getState().addCondition( '0', condA );
			getState().updateCondition( '0.0', { operator: 'LIKE' } );
			const nested = getState().root.children[ 0 ] as FilterGroup;
			const cond = nested.children[ 0 ] as FilterCondition;
			expect( cond.operator ).toBe( 'LIKE' );
		} );
	} );

	describe( 'setCombinator', () => {
		it( 'toggles root combinator to OR', () => {
			getState().setCombinator( '', 'OR' );
			expect( getState().root.combinator ).toBe( 'OR' );
		} );

		it( 'toggles nested group combinator', () => {
			getState().addGroup( '', 'AND' );
			getState().setCombinator( '0', 'OR' );
			const nested = getState().root.children[ 0 ] as FilterGroup;
			expect( nested.combinator ).toBe( 'OR' );
		} );
	} );

	describe( 'clearAll', () => {
		it( 'resets to empty AND root and clears searchQuery', () => {
			getState().addCondition( '', condA );
			getState().setSearchQuery( 'hello' );
			getState().clearAll();
			expect( getState().root ).toEqual( emptyRoot );
			expect( getState().searchQuery ).toBe( '' );
		} );
	} );

	describe( 'setSearchQuery', () => {
		it( 'updates searchQuery', () => {
			getState().setSearchQuery( 'foo bar' );
			expect( getState().searchQuery ).toBe( 'foo bar' );
		} );
	} );

	describe( 'applyPreset', () => {
		it( 'sets filters from legacy definition and converts to tree', () => {
			getState().applyPreset( {
				filters: [ { field: 'name', operator: '=', value: 'Test' } ],
				search: 'query',
				sort: { field: 'name', order: 'asc' },
			} );
			expect( getState().searchQuery ).toBe( 'query' );
			// root should contain one condition
			expect( getState().root.children ).toHaveLength( 1 );
		} );
	} );
} );

describe( 'legacyFiltersToGroup', () => {
	it( 'converts flat ProductFilter array to a FilterGroup', () => {
		const result = legacyFiltersToGroup( [
			{ field: 'name', operator: '=', value: 'Shirt' },
			{ field: 'sku', operator: 'LIKE', value: 'SKU' },
		] );
		expect( result.type ).toBe( 'group' );
		expect( result.combinator ).toBe( 'AND' );
		expect( result.children ).toHaveLength( 2 );
		expect( ( result.children[ 0 ] as FilterCondition ).field ).toBe( 'name' );
	} );

	it( 'returns empty AND group for empty input', () => {
		const result = legacyFiltersToGroup( [] );
		expect( result.children ).toHaveLength( 0 );
	} );
} );

describe( 'isEmptyRoot', () => {
	it( 'returns true for a root with no children', () => {
		const root: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		expect( isEmptyRoot( root ) ).toBe( true );
	} );

	it( 'returns false for a root that has at least one child', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA ],
		};
		expect( isEmptyRoot( root ) ).toBe( false );
	} );

	it( 'returns false for an OR root with children', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA ],
		};
		expect( isEmptyRoot( root ) ).toBe( false );
	} );
} );

describe( 'selectIsComplex', () => {
	it( 'returns false for a simple flat AND root', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, condB ],
		};
		expect( selectIsComplex( root ) ).toBe( false );
	} );

	it( 'returns true when root combinator is OR', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA, condB ],
		};
		expect( selectIsComplex( root ) ).toBe( true );
	} );

	it( 'returns true when root has a nested group child', () => {
		const nested: FilterGroup = { type: 'group', combinator: 'OR', children: [ condA ] };
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condB, nested ],
		};
		expect( selectIsComplex( root ) ).toBe( true );
	} );

	it( 'returns false for an empty AND root', () => {
		const root: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		expect( selectIsComplex( root ) ).toBe( false );
	} );
} );

describe( 'selectLegacyFilters', () => {
	it( 'returns top-level conditions from an AND root', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [
				{ type: 'condition', field: 'name', operator: '=', value: 'Shirt' },
				{ type: 'condition', field: 'sku', operator: 'LIKE', value: 'SKU' },
			],
		};
		const filters = selectLegacyFilters( root );
		expect( filters ).toHaveLength( 2 );
	} );

	it( 'returns empty array for OR root (complex tree)', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [
				{ type: 'condition', field: 'name', operator: '=', value: 'Shirt' },
			],
		};
		const filters = selectLegacyFilters( root );
		expect( filters ).toHaveLength( 0 );
	} );

	it( 'excludes nested groups from flat list', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [
				{ type: 'condition', field: 'name', operator: '=', value: 'Shirt' },
				{ type: 'group', combinator: 'OR', children: [] },
			],
		};
		const filters = selectLegacyFilters( root );
		// Only the condition is returned; the nested group is excluded.
		expect( filters ).toHaveLength( 1 );
	} );
} );
