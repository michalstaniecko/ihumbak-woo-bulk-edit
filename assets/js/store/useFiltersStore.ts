import { create } from 'zustand';
import type { ProductFilter, SavedFilterDefinition } from '@/types/api';

interface FiltersState {
	filters: ProductFilter[];
	searchQuery: string;

	setFilters: ( filters: ProductFilter[] ) => void;
	addFilter: ( filter: ProductFilter ) => void;
	removeFilter: ( index: number ) => void;
	clearAll: () => void;
	setSearchQuery: ( q: string ) => void;
	applyPreset: ( definition: SavedFilterDefinition ) => void;
}

export const useFiltersStore = create< FiltersState >()( ( set ) => ( {
	filters: [],
	searchQuery: '',

	setFilters: ( filters ) => set( { filters } ),

	addFilter: ( filter ) =>
		set( ( state ) => ( { filters: [ ...state.filters, filter ] } ) ),

	removeFilter: ( index ) =>
		set( ( state ) => ( {
			filters: state.filters.filter( ( _, i ) => i !== index ),
		} ) ),

	clearAll: () => set( { filters: [], searchQuery: '' } ),

	setSearchQuery: ( searchQuery ) => set( { searchQuery } ),

	applyPreset: ( definition ) =>
		set( {
			filters: definition.filters,
			searchQuery: definition.search ?? '',
		} ),
} ) );
