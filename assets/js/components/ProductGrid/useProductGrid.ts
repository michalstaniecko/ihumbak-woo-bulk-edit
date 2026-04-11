import { useState, useMemo, useCallback, useRef } from 'react';
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
import { createColumns } from './columnFactory';
import type { GridPaginationState } from '@/types/grid';
import type { Product, Sort } from '@/types/api';

interface UseProductGridReturn {
	table: Table< Product >;
	isLoading: boolean;
	isFetching: boolean;
	pagination: GridPaginationState;
	setPagination: ( pagination: GridPaginationState ) => void;
	totalItems: number;
	totalPages: number;
	selectedCount: number;
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
		filters: [],
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
	};
}
