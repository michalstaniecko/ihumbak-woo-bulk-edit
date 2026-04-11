import { useProductGrid } from './useProductGrid';
import { HeaderRow } from './HeaderRow';
import { VirtualizedBody } from './VirtualizedBody';
import { Pagination } from './Pagination';
import { StatusBar } from './StatusBar';
import { LoadingSkeleton } from './LoadingSkeleton';
import { getColumnWidths } from './columnFactory';
import { useGridKeyboardNav } from '@/hooks/useGridKeyboardNav';
import { useBatchSave } from '@/hooks/useBatchSave';

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
	} = useProductGrid();

	const batchSave = useBatchSave();

	useGridKeyboardNav( table, fields );

	if ( isLoading ) {
		return <LoadingSkeleton />;
	}

	const columnWidths = getColumnWidths(
		table.getAllColumns().map( ( c ) => c.columnDef )
	);

	return (
		<div className="iwbe-product-grid">
			<div className="iwbe-grid-header-wrapper">
				<HeaderRow table={ table } columnWidths={ columnWidths } />
			</div>

			<div className="iwbe-grid-body-wrapper">
				<VirtualizedBody
					table={ table }
					columnWidths={ columnWidths }
					page={ pagination.page }
				/>
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
					batchSave={ batchSave }
				/>
			</div>
		</div>
	);
}
