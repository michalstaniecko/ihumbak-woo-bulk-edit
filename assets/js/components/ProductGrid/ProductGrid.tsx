import { useCallback, useState } from 'react';
import { useProductGrid } from './useProductGrid';
import { HeaderRow } from './HeaderRow';
import { VirtualizedBody } from './VirtualizedBody';
import { Pagination } from './Pagination';
import { StatusBar } from './StatusBar';
import { LoadingSkeleton } from './LoadingSkeleton';
import { FilterToolbar } from './FilterToolbar';
import { getColumnWidths } from './columnFactory';
import { useGridKeyboardNav } from '@/hooks/useGridKeyboardNav';
import { useBatchSave } from '@/hooks/useBatchSave';
import { BulkEditModal } from './bulkEdit';
import type { BulkDeleteResponse, Field } from '@/types/api';

export function ProductGrid(): JSX.Element {
	const {
		table,
		isLoading,
		isFetching,
		pagination,
		setPagination,
		totalItems,
		totalPages,
		selectedCount,
		fields,
		products,
		filters,
		effectiveFilters,
		sort,
		searchQuery,
		onSearchChange,
		onAddFilter,
		onRemoveFilter,
		onClearAllFilters,
	} = useProductGrid();

	const batchSave = useBatchSave();
	const [ bulkEditField, setBulkEditField ] = useState< Field | null >( null );

	useGridKeyboardNav( table, fields );

	const handleDeleted = useCallback(
		( _result: BulkDeleteResponse ) => {
			table.resetRowSelection();
		},
		[ table ]
	);

	if ( isLoading ) {
		return <LoadingSkeleton />;
	}

	const columnWidths = getColumnWidths(
		table.getAllColumns().map( ( c ) => c.columnDef )
	);
	const totalWidth = columnWidths.reduce( ( sum, w ) => sum + w, 0 );

	const selectedProducts = table
		.getSelectedRowModel()
		.rows.map( ( row ) => row.original );

	return (
		<div className="iwbe-product-grid">
			<FilterToolbar
				fields={ fields }
				filters={ filters }
				searchQuery={ searchQuery }
				onSearchChange={ onSearchChange }
				onAddFilter={ onAddFilter }
				onRemoveFilter={ onRemoveFilter }
				onClearAll={ onClearAllFilters }
			/>

			<div className="iwbe-grid-scroll-container">
				<div
					className="iwbe-grid-inner"
					style={ { width: totalWidth } }
				>
					<div className="iwbe-grid-header-wrapper">
						<HeaderRow
							table={ table }
							columnWidths={ columnWidths }
							onBulkEdit={ ( field ) => setBulkEditField( field ) }
						/>
					</div>

					<div className="iwbe-grid-body-wrapper">
						<VirtualizedBody
							table={ table }
							columnWidths={ columnWidths }
							page={ pagination.page }
						/>
					</div>
				</div>
			</div>

			<div className="iwbe-grid-footer">
				<Pagination
					page={ pagination.page }
					totalPages={ totalPages }
					perPage={ pagination.perPage }
					onPageChange={ ( page ) =>
						setPagination( { ...pagination, page } )
					}
					onPerPageChange={ ( perPage ) =>
						setPagination( { page: 1, perPage } )
					}
					isLoading={ isFetching }
				/>
				<StatusBar
					total={ totalItems }
					selectedCount={ selectedCount }
					isFetching={ isFetching && ! isLoading }
					products={ products }
					selectedProducts={ selectedProducts }
					batchSave={ batchSave }
					onDeleted={ handleDeleted }
				/>
			</div>

			{ bulkEditField && (
				<BulkEditModal
					field={ bulkEditField }
					selectedProducts={ selectedProducts }
					totalFiltered={ totalItems }
					filters={ effectiveFilters }
					sort={ sort }
					onClose={ () => setBulkEditField( null ) }
				/>
			) }
		</div>
	);
}
