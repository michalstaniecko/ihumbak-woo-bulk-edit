import { useState, useMemo, useCallback, useRef, useEffect } from 'react';
import {
	useReactTable,
	getCoreRowModel,
} from '@tanstack/react-table';
import type {
	SortingState,
	RowSelectionState,
	OnChangeFn,
	Table,
	Row,
} from '@tanstack/react-table';
import { useProducts } from '@/hooks/useProducts';
import { useFields } from '@/hooks/useFields';
import { useVariations } from '@/hooks/useVariations';
import { createColumns } from './columnFactory';
import type { GridPaginationState } from '@/types/grid';
import type { Field, Product, ProductFilter, Sort, VariationsResponse, SavedFilterDefinition } from '@/types/api';
// VariationsResponse is used by the variationsMap type in the return shape.
import { useExpansionStore, useFiltersStore } from '@/store';

export interface UseProductGridReturn {
	table: Table< Product >;
	isLoading: boolean;
	isFetching: boolean;
	pagination: GridPaginationState;
	setPagination: ( pagination: GridPaginationState ) => void;
	totalItems: number;
	totalPages: number;
	selectedCount: number;
	fields: Field[];
	products: Product[];
	filters: ProductFilter[];
	effectiveFilters: ProductFilter[];
	sort: Sort;
	searchQuery: string;
	onSearchChange: ( query: string ) => void;
	onAddFilter: ( filter: ProductFilter ) => void;
	onRemoveFilter: ( index: number ) => void;
	onClearAllFilters: () => void;
	onApplyPreset: ( definition: SavedFilterDefinition ) => void;
	expandedSet: Set< number >;
	variationsMap: Map< number, VariationsResponse >;
}

function sortingStateToApiSort( sorting: SortingState ): Sort {
	if ( sorting.length === 0 ) {
		return { field: 'name', order: 'asc' };
	}
	const first = sorting[ 0 ];
	return {
		field: first.id,
		order: first.desc ? 'desc' : 'asc',
	};
}

export function useProductGrid(): UseProductGridReturn {
	const [ sorting, setSorting ] = useState< SortingState >( [] );
	const [ pagination, setPaginationState ] = useState< GridPaginationState >( {
		page: 1,
		perPage: 50,
	} );
	const [ rowSelection, setRowSelection ] = useState< RowSelectionState >( {} );
	const lastSelectedIndexRef = useRef< number | null >( null );

	// Filter state — delegated to useFiltersStore so SavedFiltersMenu can call applyPreset
	const filters = useFiltersStore( ( s ) => s.filters );
	const searchQuery = useFiltersStore( ( s ) => s.searchQuery );
	const setFilters = useFiltersStore( ( s ) => s.setFilters );
	const storeAddFilter = useFiltersStore( ( s ) => s.addFilter );
	const storeRemoveFilter = useFiltersStore( ( s ) => s.removeFilter );
	const storeClearAll = useFiltersStore( ( s ) => s.clearAll );
	const storeSetSearchQuery = useFiltersStore( ( s ) => s.setSearchQuery );
	const storeApplyPreset = useFiltersStore( ( s ) => s.applyPreset );

	const [ debouncedSearch, setDebouncedSearch ] = useState( searchQuery );

	// Debounce search input (300ms)
	useEffect( () => {
		const timer = setTimeout( () => {
			setDebouncedSearch( searchQuery );
		}, 300 );
		return () => clearTimeout( timer );
	}, [ searchQuery ] );

	// Reset to page 1 when filters or search change
	useEffect( () => {
		setPaginationState( ( prev ) => ( { ...prev, page: 1 } ) );
	}, [ filters, debouncedSearch ] );

	// Combine explicit filters with search query filters
	const apiFilters = useMemo( () => {
		const combined = [ ...filters ];
		if ( debouncedSearch.trim() ) {
			combined.push( {
				field: 'name',
				operator: 'LIKE',
				value: debouncedSearch.trim(),
			} );
		}
		return combined;
	}, [ filters, debouncedSearch ] );

	const onSearchChange = useCallback( ( query: string ) => {
		storeSetSearchQuery( query );
	}, [ storeSetSearchQuery ] );

	const onAddFilter = useCallback( ( filter: ProductFilter ) => {
		storeAddFilter( filter );
	}, [ storeAddFilter ] );

	const onRemoveFilter = useCallback( ( index: number ) => {
		storeRemoveFilter( index );
	}, [ storeRemoveFilter ] );

	const onClearAllFilters = useCallback( () => {
		storeClearAll();
	}, [ storeClearAll ] );

	const onApplyPreset = useCallback( ( definition: SavedFilterDefinition ) => {
		storeApplyPreset( definition );
	}, [ storeApplyPreset ] );

	// Expansion state for variable products.
	const expandedSet = useExpansionStore( ( s ) => s.expanded );

	// Sort expanded IDs to keep useQueries hook count stable.
	const expandedIds = useMemo(
		() => [ ...expandedSet ].sort( ( a, b ) => a - b ),
		[ expandedSet ]
	);

	// Fetch variations for all expanded parents in parallel.
	const variationsMap = useVariations( expandedIds );

	const { data: fields } = useFields();
	const apiSort = useMemo( () => sortingStateToApiSort( sorting ), [ sorting ] );

	const {
		data: productsData,
		isLoading,
		isFetching,
	} = useProducts( {
		sort: apiSort,
		pagination: {
			page: pagination.page,
			per_page: pagination.perPage,
		},
		filters: apiFilters,
	} );

	const columns = useMemo(
		() => createColumns( fields ?? [] ),
		[ fields ]
	);

	const handleSortingChange: OnChangeFn< SortingState > = useCallback(
		( updater ) => {
			setSorting( updater );
			setPaginationState( ( prev ) => ( { ...prev, page: 1 } ) );
		},
		[]
	);

	const handleRowSelectionChange: OnChangeFn< RowSelectionState > = useCallback(
		( updater ) => {
			setRowSelection( updater );
		},
		[]
	);

	const setPagination = useCallback(
		( newPagination: GridPaginationState ) => {
			setPaginationState( newPagination );
		},
		[]
	);

	const table = useReactTable( {
		data: productsData?.items ?? [],
		columns,
		state: {
			sorting,
			rowSelection,
		},
		onSortingChange: handleSortingChange,
		onRowSelectionChange: handleRowSelectionChange,
		getCoreRowModel: getCoreRowModel(),
		manualSorting: true,
		manualPagination: true,
		enableRowSelection: true,
		enableMultiRowSelection: true,
		getRowId: ( row ) => String( row.id ),
	} );

	const handleShiftClick = useCallback(
		( row: Row< Product >, event: React.MouseEvent ) => {
			const rows = table.getRowModel().rows;
			const currentIndex = rows.findIndex( ( r ) => r.id === row.id );

			if ( event.shiftKey && lastSelectedIndexRef.current !== null ) {
				const start = Math.min( lastSelectedIndexRef.current, currentIndex );
				const end = Math.max( lastSelectedIndexRef.current, currentIndex );
				const newSelection: RowSelectionState = { ...rowSelection };

				for ( let i = start; i <= end; i++ ) {
					newSelection[ rows[ i ].id ] = true;
				}

				setRowSelection( newSelection );
			} else {
				row.toggleSelected();
			}

			lastSelectedIndexRef.current = currentIndex;
		},
		[ table, rowSelection ]
	);

	// Attach shift-click handler to table meta for use by GridRow
	table.options.meta = {
		...( table.options.meta ?? {} ),
		handleShiftClick,
	};

	const selectedCount = Object.keys( rowSelection ).filter(
		( key ) => rowSelection[ key ]
	).length;

	return {
		table,
		isLoading,
		isFetching,
		pagination,
		setPagination,
		totalItems: productsData?.total ?? 0,
		totalPages: productsData?.pages ?? 0,
		selectedCount,
		fields: fields ?? [],
		products: productsData?.items ?? [],
		filters,
		effectiveFilters: apiFilters,
		sort: apiSort,
		searchQuery,
		onSearchChange,
		onAddFilter,
		onRemoveFilter,
		onClearAllFilters,
		onApplyPreset,
		expandedSet,
		variationsMap,
	};
}
