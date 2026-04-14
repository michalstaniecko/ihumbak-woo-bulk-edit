import { describe, it, expect, beforeEach } from 'vitest';
import { useFiltersStore } from '../useFiltersStore';
import type { ProductFilter } from '@/types/api';

function getState() {
	return useFiltersStore.getState();
}

function reset() {
	useFiltersStore.setState( {
		filters: [],
		searchQuery: '',
	} );
}

const filterA: ProductFilter = { field: 'name', operator: '=', value: 'Test' };
const filterB: ProductFilter = { field: 'sku', operator: 'LIKE', value: 'SKU-' };

describe( 'useFiltersStore', () => {
	beforeEach( () => {
		reset();
	} );

	describe( 'setFilters', () => {
		it( 'replaces the entire filters array', () => {
			getState().setFilters( [ filterA, filterB ] );
			expect( getState().filters ).toEqual( [ filterA, filterB ] );
		} );

		it( 'can be called with an empty array to clear filters', () => {
			getState().setFilters( [ filterA ] );
			getState().setFilters( [] );
			expect( getState().filters ).toHaveLength( 0 );
		} );
	} );

	describe( 'addFilter', () => {
		it( 'appends a filter to the array', () => {
			getState().addFilter( filterA );
			getState().addFilter( filterB );
			expect( getState().filters ).toEqual( [ filterA, filterB ] );
		} );
	} );

	describe( 'removeFilter', () => {
		it( 'removes filter at given index', () => {
			getState().setFilters( [ filterA, filterB ] );
			getState().removeFilter( 0 );
			expect( getState().filters ).toEqual( [ filterB ] );
		} );

		it( 'is a no-op for an out-of-range index', () => {
			getState().setFilters( [ filterA ] );
			getState().removeFilter( 99 );
			expect( getState().filters ).toEqual( [ filterA ] );
		} );
	} );

	describe( 'clearAll', () => {
		it( 'clears both filters and searchQuery', () => {
			getState().setFilters( [ filterA ] );
			getState().setSearchQuery( 'hello' );

			getState().clearAll();

			expect( getState().filters ).toHaveLength( 0 );
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
		it( 'sets filters and search from definition', () => {
			getState().applyPreset( {
				filters: [ filterA ],
				search: 'query',
				sort: { field: 'name', order: 'asc' },
			} );

			expect( getState().filters ).toEqual( [ filterA ] );
			expect( getState().searchQuery ).toBe( 'query' );
		} );

		it( 'works with minimal definition (no search/sort)', () => {
			getState().applyPreset( { filters: [ filterB ] } );
			expect( getState().filters ).toEqual( [ filterB ] );
			expect( getState().searchQuery ).toBe( '' );
		} );
	} );
} );
