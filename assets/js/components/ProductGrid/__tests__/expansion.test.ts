import { describe, it, expect, beforeEach } from 'vitest';
import { useExpansionStore } from '@/store/useExpansionStore';
import { buildDisplayRows } from '../displayRows';
import type { Variation } from '@/types/api';

// ── Helpers ──────────────────────────────────────────────────────────────────

function resetExpansion() {
	useExpansionStore.setState( { expanded: new Set< number >() } );
}

function makeVariation( id: number, parentId: number ): Variation {
	return {
		id,
		parent_id: parentId,
		name: `Variation ${ id }`,
		sku: `SKU-${ id }`,
		regular_price: '10.00',
		sale_price: '',
		stock_quantity: null,
		manage_stock: false,
		weight: '',
		length: '',
		width: '',
		height: '',
		thumbnail_id: null,
		status: 'publish',
		menu_order: 0,
		attributes: {},
		post_modified: '2025-01-01 00:00:00',
	};
}

// Minimal row stub — only the fields buildDisplayRows actually reads.
function makeRow( id: number, type: string = 'simple' ) {
	return {
		id: String( id ),
		original: { id, type, variations_count: 0 },
	} as unknown as import('@tanstack/react-table').Row< import('@/types/api').Product >;
}

function makeVariableRow( id: number, variationsCount: number ) {
	return {
		id: String( id ),
		original: { id, type: 'variable', variations_count: variationsCount },
	} as unknown as import('@tanstack/react-table').Row< import('@/types/api').Product >;
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe( 'buildDisplayRows', () => {
	beforeEach( () => {
		resetExpansion();
	} );

	it( 'returns only parent rows when no product is expanded', () => {
		const rows = [ makeRow( 1 ), makeVariableRow( 2, 3 ) ];
		const variationsMap = new Map< number, Variation[] >();

		const display = buildDisplayRows( rows, new Set(), variationsMap );

		expect( display ).toHaveLength( 2 );
		expect( display.every( ( r ) => r.kind === 'parent' ) ).toBe( true );
	} );

	it( 'injects variation rows after the parent when parent is expanded', () => {
		const rows = [ makeVariableRow( 10, 2 ) ];
		const v1 = makeVariation( 101, 10 );
		const v2 = makeVariation( 102, 10 );
		const variationsMap = new Map( [ [ 10, [ v1, v2 ] ] ] );

		const display = buildDisplayRows( rows, new Set( [ 10 ] ), variationsMap );

		expect( display ).toHaveLength( 3 );
		expect( display[ 0 ].kind ).toBe( 'parent' );
		expect( display[ 1 ].kind ).toBe( 'variation' );
		expect( display[ 2 ].kind ).toBe( 'variation' );

		if ( display[ 1 ].kind === 'variation' ) {
			expect( display[ 1 ].variation.id ).toBe( 101 );
		}
		if ( display[ 2 ].kind === 'variation' ) {
			expect( display[ 2 ].variation.id ).toBe( 102 );
		}
	} );

	it( 'does not inject variations when parent is not expanded', () => {
		const rows = [ makeVariableRow( 10, 2 ) ];
		const v1 = makeVariation( 101, 10 );
		const variationsMap = new Map( [ [ 10, [ v1 ] ] ] );

		const display = buildDisplayRows( rows, new Set(), variationsMap );

		expect( display ).toHaveLength( 1 );
		expect( display[ 0 ].kind ).toBe( 'parent' );
	} );

	it( 'marks parent as expanded when in expandedSet', () => {
		const rows = [ makeVariableRow( 7, 1 ) ];
		const variationsMap = new Map< number, Variation[] >();

		const display = buildDisplayRows( rows, new Set( [ 7 ] ), variationsMap );

		expect( display[ 0 ].kind ).toBe( 'parent' );
		if ( display[ 0 ].kind === 'parent' ) {
			expect( display[ 0 ].isExpanded ).toBe( true );
		}
	} );

	it( 'marks parent as loading when expanded but variations not yet fetched', () => {
		const rows = [ makeVariableRow( 7, 2 ) ];
		const variationsMap = new Map< number, Variation[] >();

		const display = buildDisplayRows( rows, new Set( [ 7 ] ), variationsMap );

		expect( display[ 0 ].kind ).toBe( 'parent' );
		if ( display[ 0 ].kind === 'parent' ) {
			// Variations map has no entry yet — isLoadingVariations should be true
			// (indicates a pending fetch).
			expect( display[ 0 ].isLoadingVariations ).toBe( true );
		}
	} );

	it( 'multiple parent rows with mixed expansion state', () => {
		const rows = [
			makeVariableRow( 1, 2 ),
			makeRow( 2 ), // simple
			makeVariableRow( 3, 1 ),
		];
		const v1a = makeVariation( 11, 1 );
		const v1b = makeVariation( 12, 1 );
		const v3a = makeVariation( 31, 3 );
		const variationsMap = new Map( [
			[ 1, [ v1a, v1b ] ],
			[ 3, [ v3a ] ],
		] );

		// Only product 1 is expanded.
		const display = buildDisplayRows( rows, new Set( [ 1 ] ), variationsMap );

		// Rows: parent(1), var(11), var(12), parent(2), parent(3)
		expect( display ).toHaveLength( 5 );
		expect( display[ 0 ].kind ).toBe( 'parent' );
		expect( display[ 1 ].kind ).toBe( 'variation' );
		expect( display[ 2 ].kind ).toBe( 'variation' );
		expect( display[ 3 ].kind ).toBe( 'parent' );
		expect( display[ 4 ].kind ).toBe( 'parent' );
	} );

	it( 'variation row carries parentId', () => {
		const rows = [ makeVariableRow( 20, 1 ) ];
		const v = makeVariation( 201, 20 );
		const variationsMap = new Map( [ [ 20, [ v ] ] ] );

		const display = buildDisplayRows( rows, new Set( [ 20 ] ), variationsMap );

		expect( display[ 1 ].kind ).toBe( 'variation' );
		if ( display[ 1 ].kind === 'variation' ) {
			expect( display[ 1 ].parentId ).toBe( 20 );
		}
	} );
} );
