import { describe, it, expect } from 'vitest';
import type { FilterGroup, FilterCondition } from '@/types/api';

// Import the pure functions under test — no mocks needed
const { buildSavedFilterDefinition, stripEmptyGroups, isEffectivelyEmpty } =
	await import( '../useSaveCurrentFilter' );

// ── Helpers ───────────────────────────────────────────────────────────────────

const condA: FilterCondition = { type: 'condition', field: 'name', operator: '=', value: 'Shirt' };
const condB: FilterCondition = { type: 'condition', field: 'sku', operator: 'LIKE', value: 'SKU' };

// ── Tests for buildSavedFilterDefinition (pure function) ─────────────────────

describe( 'buildSavedFilterDefinition', () => {

	it( 'serializes a flat AND-only root as a ProductFilter[] (legacy shape)', () => {
		const flatAndRoot: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, condB ],
		};

		const definition = buildSavedFilterDefinition( flatAndRoot, '' );

		expect( Array.isArray( definition.filters ) ).toBe( true );
		const filters = definition.filters as Array< { field: string } >;
		expect( filters ).toHaveLength( 2 );
		expect( filters[ 0 ].field ).toBe( 'name' );
		expect( filters[ 1 ].field ).toBe( 'sku' );
	} );

	it( 'serializes an OR root as a FilterGroup tree', () => {
		const orRoot: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA, condB ],
		};

		const definition = buildSavedFilterDefinition( orRoot, '' );

		expect( Array.isArray( definition.filters ) ).toBe( false );
		const filters = definition.filters as FilterGroup;
		expect( filters.type ).toBe( 'group' );
		expect( filters.combinator ).toBe( 'OR' );
	} );

	it( 'serializes a nested-group AND root as a FilterGroup tree', () => {
		const nestedGroup: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA, condB ],
		};
		const andRootWithNested: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, nestedGroup ],
		};

		const definition = buildSavedFilterDefinition( andRootWithNested, '' );

		expect( Array.isArray( definition.filters ) ).toBe( false );
		const filters = definition.filters as FilterGroup;
		expect( filters.type ).toBe( 'group' );
		expect( filters.combinator ).toBe( 'AND' );
	} );

	it( 'includes the search string in the definition', () => {
		const flatRoot: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA ],
		};

		const definition = buildSavedFilterDefinition( flatRoot, 'my search' );

		expect( definition.search ).toBe( 'my search' );
	} );

	it( 'serializes an empty AND root as an empty ProductFilter[] (not a group)', () => {
		const emptyRoot: FilterGroup = { type: 'group', combinator: 'AND', children: [] };

		const definition = buildSavedFilterDefinition( emptyRoot, '' );

		// Empty AND root is not complex, so it becomes [] (legacy shape).
		expect( Array.isArray( definition.filters ) ).toBe( true );
		expect( ( definition.filters as unknown[] ) ).toHaveLength( 0 );
	} );
} );

// ── Additional wiring tests via buildSavedFilterDefinition ───────────────────
// NOTE: The hook itself (useSaveCurrentFilter) uses React hooks (useCallback)
// and cannot be called directly in a 'node' environment without a renderer.
// These tests cover the same serialization decisions from the hook's perspective
// by constructing the payload the same way the hook would.

describe( 'useSaveCurrentFilter — payload construction', () => {
	it( 'flat AND root → payload.definition.filters is a ProductFilter[]', () => {
		const flatRoot: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, condB ],
		};

		const definition = buildSavedFilterDefinition( flatRoot, '' );
		const payload = { name: 'Test', is_shared: false, definition };

		expect( Array.isArray( payload.definition.filters ) ).toBe( true );
		const filters = payload.definition.filters as Array< { field: string } >;
		expect( filters ).toHaveLength( 2 );
	} );

	it( 'OR root → payload.definition.filters is a FilterGroup', () => {
		const orRoot: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA, condB ],
		};

		const definition = buildSavedFilterDefinition( orRoot, '' );
		expect( Array.isArray( definition.filters ) ).toBe( false );
		expect( ( definition.filters as FilterGroup ).combinator ).toBe( 'OR' );
	} );

	it( 'nested group → payload.definition.filters is a FilterGroup', () => {
		const nested: FilterGroup = { type: 'group', combinator: 'OR', children: [ condA ] };
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condB, nested ],
		};

		const definition = buildSavedFilterDefinition( root, '' );
		expect( Array.isArray( definition.filters ) ).toBe( false );
		expect( ( definition.filters as FilterGroup ).type ).toBe( 'group' );
	} );
} );

// ── stripEmptyGroups ─────────────────────────────────────────────────────────

describe( 'stripEmptyGroups', () => {
	it( 'removes an empty nested group while keeping conditions', () => {
		const emptyGroup: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, emptyGroup ],
		};

		const result = stripEmptyGroups( root );

		expect( result.children ).toHaveLength( 1 );
		expect( result.children[ 0 ] ).toEqual( condA );
	} );

	it( 'removes multiple empty nested groups', () => {
		const emptyA: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		const emptyB: FilterGroup = { type: 'group', combinator: 'OR', children: [] };
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, emptyA, emptyB, condB ],
		};

		const result = stripEmptyGroups( root );

		expect( result.children ).toHaveLength( 2 );
	} );

	it( 'keeps non-empty nested groups', () => {
		const nonEmptyGroup: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA ],
		};
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condB, nonEmptyGroup ],
		};

		const result = stripEmptyGroups( root );

		expect( result.children ).toHaveLength( 2 );
	} );

	it( 'recursively strips empty groups from nested groups', () => {
		const deepEmpty: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		const midGroup: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA, deepEmpty ],
		};
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ midGroup ],
		};

		const result = stripEmptyGroups( root );

		const mid = result.children[ 0 ] as FilterGroup;
		expect( mid.children ).toHaveLength( 1 );
		expect( mid.children[ 0 ] ).toEqual( condA );
	} );

	it( 'does not modify a root that has no empty groups', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, condB ],
		};

		const result = stripEmptyGroups( root );

		expect( result.children ).toHaveLength( 2 );
	} );
} );

// ── isEffectivelyEmpty ────────────────────────────────────────────────────────

describe( 'isEffectivelyEmpty', () => {
	it( 'returns true for a root with no children', () => {
		const root: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		expect( isEffectivelyEmpty( root ) ).toBe( true );
	} );

	it( 'returns true for a root with only empty nested groups', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [
				{ type: 'group', combinator: 'AND', children: [] },
				{ type: 'group', combinator: 'OR', children: [] },
			],
		};
		expect( isEffectivelyEmpty( root ) ).toBe( true );
	} );

	it( 'returns false for a root with a condition', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA ],
		};
		expect( isEffectivelyEmpty( root ) ).toBe( false );
	} );

	it( 'returns false for a root with an empty group AND a condition', () => {
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [
				condA,
				{ type: 'group', combinator: 'AND', children: [] },
			],
		};
		expect( isEffectivelyEmpty( root ) ).toBe( false );
	} );
} );

// ── buildSavedFilterDefinition strips empty groups ────────────────────────────

describe( 'buildSavedFilterDefinition — empty group stripping', () => {
	it( 'strips empty nested group before serializing; result is ProductFilter[] (regression)', () => {
		// This is the exact scenario the user reported:
		// condition + accidentally-added empty group → should save as ProductFilter[]
		const emptyGroup: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		const root: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ condA, emptyGroup ],
		};

		const definition = buildSavedFilterDefinition( root, '' );

		// After stripping, root has 1 condition → flat AND → ProductFilter[]
		expect( Array.isArray( definition.filters ) ).toBe( true );
		const filters = definition.filters as Array< { field: string } >;
		expect( filters ).toHaveLength( 1 );
		expect( filters[ 0 ].field ).toBe( 'name' );
	} );

	it( 'strips empty nested group when serializing OR root', () => {
		const emptyGroup: FilterGroup = { type: 'group', combinator: 'AND', children: [] };
		const root: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [ condA, emptyGroup ],
		};

		const definition = buildSavedFilterDefinition( root, '' );

		// After stripping: OR root with 1 condition → FilterGroup tree
		expect( Array.isArray( definition.filters ) ).toBe( false );
		const filters = definition.filters as FilterGroup;
		expect( filters.combinator ).toBe( 'OR' );
		expect( filters.children ).toHaveLength( 1 );
	} );
} );
