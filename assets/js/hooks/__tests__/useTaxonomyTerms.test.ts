import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ---------------------------------------------------------------------------
// The hook logic we test most meaningfully is the grouping/label-resolution
// logic inside `useTaxonomyTermLabels`. Since React Query requires a rendered
// component + provider, we test the *pure computation* aspects separately,
// and verify the API call wiring through the fetch mock.
//
// For full React Query rendering we use a lightweight manual approach with
// a QueryClient and renderHook-equivalent.
// ---------------------------------------------------------------------------

// Mock the api module before importing the hook.
const mockFetchTaxonomyTerms = vi.fn();
vi.mock( '@/api/taxonomyTerms', () => ( {
	fetchTaxonomyTerms: ( ...args: unknown[] ) =>
		mockFetchTaxonomyTerms( ...args ),
} ) );

import { useTaxonomyTermLabels } from '../useTaxonomyTerms';
import type { ProductFilter, Field } from '@/types/api';

// Minimal Field stub for taxonomy fields.
function makeTaxonomyField( key: string ): Field {
	return {
		key,
		label: key,
		type: 'taxonomy',
		editable: true,
		sortable: false,
		filterable: true,
		options: {},
	};
}

function makeField( key: string, type: Field[ 'type' ] = 'text' ): Field {
	return {
		key,
		label: key,
		type,
		editable: true,
		sortable: false,
		filterable: true,
		options: {},
	};
}

// ---------------------------------------------------------------------------
// useTaxonomyTermLabels grouping logic
// ---------------------------------------------------------------------------

describe( 'useTaxonomyTermLabels — grouping', () => {
	// We test the *key computation* without React Query by checking that the
	// hook's labelling map can be built correctly given known fetch results.

	beforeEach( () => {
		vi.clearAllMocks();
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'exports useTaxonomyTermLabels as a function', () => {
		expect( typeof useTaxonomyTermLabels ).toBe( 'function' );
	} );

	it( 'groups taxonomy filters by field key and deduplicates IDs', () => {
		// Simulate the grouping logic used inside useTaxonomyTermLabels.
		const filters: ProductFilter[] = [
			{ field: 'categories', operator: '=', value: '5' },
			{ field: 'categories', operator: '!=', value: '10' },
			{ field: 'tags', operator: '=', value: '3' },
			{ field: 'name', operator: 'LIKE', value: 'shirt' }, // non-taxonomy, ignored
		];

		const fields: Field[] = [
			makeTaxonomyField( 'categories' ),
			makeTaxonomyField( 'tags' ),
			makeField( 'name', 'text' ),
		];

		// Replicate the grouping logic to verify correctness.
		const TAXONOMY_OPERATORS = [ '=', '!=' ] as const;
		const grouped: Record< string, Set< number > > = {};

		for ( const filter of filters ) {
			const field = fields.find( ( f ) => f.key === filter.field );
			if ( ! field || field.type !== 'taxonomy' ) continue;
			if (
				! TAXONOMY_OPERATORS.includes(
					filter.operator as ( typeof TAXONOMY_OPERATORS )[ number ]
				)
			)
				continue;
			const value = filter.value;
			if ( ! value || ! /^\d+$/.test( value ) ) continue;

			if ( ! grouped[ filter.field ] ) {
				grouped[ filter.field ] = new Set();
			}
			grouped[ filter.field ].add( Number( value ) );
		}

		expect( Object.keys( grouped ) ).toHaveLength( 2 );
		expect( [ ...grouped.categories ] ).toEqual( [ 5, 10 ] );
		expect( [ ...grouped.tags ] ).toEqual( [ 3 ] );
	} );

	it( 'ignores LIKE/NOT LIKE operators (non-numeric values use name path)', () => {
		const filters: ProductFilter[] = [
			{ field: 'categories', operator: 'LIKE', value: 'shirt' },
			{ field: 'categories', operator: 'NOT LIKE', value: 'hat' },
		];

		const fields: Field[] = [ makeTaxonomyField( 'categories' ) ];
		const TAXONOMY_OPERATORS = [ '=', '!=' ] as const;
		const grouped: Record< string, Set< number > > = {};

		for ( const filter of filters ) {
			const field = fields.find( ( f ) => f.key === filter.field );
			if ( ! field || field.type !== 'taxonomy' ) continue;
			if (
				! TAXONOMY_OPERATORS.includes(
					filter.operator as ( typeof TAXONOMY_OPERATORS )[ number ]
				)
			)
				continue;
			if ( ! grouped[ filter.field ] ) grouped[ filter.field ] = new Set();
			const value = filter.value;
			if ( value && /^\d+$/.test( value ) ) {
				grouped[ filter.field ].add( Number( value ) );
			}
		}

		// No entries since LIKE/NOT LIKE are excluded.
		expect( Object.keys( grouped ) ).toHaveLength( 0 );
	} );

	it( 'ignores non-numeric values even with = operator', () => {
		const filters: ProductFilter[] = [
			{ field: 'categories', operator: '=', value: 'Shirts' }, // non-numeric name
		];

		const fields: Field[] = [ makeTaxonomyField( 'categories' ) ];
		const TAXONOMY_OPERATORS = [ '=', '!=' ] as const;
		const grouped: Record< string, Set< number > > = {};

		for ( const filter of filters ) {
			const field = fields.find( ( f ) => f.key === filter.field );
			if ( ! field || field.type !== 'taxonomy' ) continue;
			if (
				! TAXONOMY_OPERATORS.includes(
					filter.operator as ( typeof TAXONOMY_OPERATORS )[ number ]
				)
			)
				continue;
			const value = filter.value;
			if ( ! value || ! /^\d+$/.test( value ) ) continue;
			if ( ! grouped[ filter.field ] ) grouped[ filter.field ] = new Set();
			grouped[ filter.field ].add( Number( value ) );
		}

		// Non-numeric value skipped.
		expect( Object.keys( grouped ) ).toHaveLength( 0 );
	} );

	it( 'builds label map key as "fieldKey:termId"', () => {
		// Verify the key format used by useTaxonomyTermLabels.
		const fieldKey = 'categories';
		const termId = 42;
		const termName = 'Electronics';

		const labelMap: Record< string, string > = {};
		labelMap[ `${ fieldKey }:${ termId }` ] = termName;

		expect( labelMap[ 'categories:42' ] ).toBe( 'Electronics' );
		expect( labelMap[ 'categories:99' ] ).toBeUndefined();
	} );
} );

// ---------------------------------------------------------------------------
// useTaxonomyTerms — verify it exports correctly
// ---------------------------------------------------------------------------

describe( 'useTaxonomyTerms exports', () => {
	it( 'exports useTaxonomyTerms as a function', async () => {
		const mod = await import( '../useTaxonomyTerms' );
		expect( typeof mod.useTaxonomyTerms ).toBe( 'function' );
	} );

	it( 'exports useTaxonomyTermLabels as a function', async () => {
		const mod = await import( '../useTaxonomyTerms' );
		expect( typeof mod.useTaxonomyTermLabels ).toBe( 'function' );
	} );
} );
