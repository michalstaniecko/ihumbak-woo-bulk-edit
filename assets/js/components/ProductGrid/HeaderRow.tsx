import { flexRender } from '@tanstack/react-table';
import type { Table } from '@tanstack/react-table';
import type { Product } from '@/types/api';
import type { SelectionColumnMeta } from './columnFactory';

interface HeaderRowProps {
	table: Table< Product >;
	columnWidths: number[];
}

function isSelectionColumn( meta: unknown ): meta is SelectionColumnMeta {
	return (
		meta !== null &&
		meta !== undefined &&
		typeof meta === 'object' &&
		'isSelection' in meta &&
		( meta as SelectionColumnMeta ).isSelection === true
	);
}

export function HeaderRow( { table, columnWidths }: HeaderRowProps ): JSX.Element {
	return (
		<div className="iwbe-header-row">
			{ table.getHeaderGroups().map( ( headerGroup ) =>
				headerGroup.headers.map( ( header, headerIndex ) => {
					const canSort = header.column.getCanSort();
					const sorted = header.column.getIsSorted();
					const meta = header.column.columnDef.meta;
					const width = columnWidths[ headerIndex ] ?? 150;

					let sortClass = '';
					if ( sorted === 'asc' ) {
						sortClass = ' iwbe-sort-asc';
					} else if ( sorted === 'desc' ) {
						sortClass = ' iwbe-sort-desc';
					}

					if ( isSelectionColumn( meta ) ) {
						return (
							<div
								key={ header.id }
								className="iwbe-th iwbe-th-select"
								style={ { width, minWidth: width } }
							>
								<input
									type="checkbox"
									checked={ table.getIsAllPageRowsSelected() }
									ref={ ( el ) => {
										if ( el ) {
											el.indeterminate =
												table.getIsSomePageRowsSelected();
										}
									} }
									onChange={ table.getToggleAllPageRowsSelectedHandler() }
								/>
							</div>
						);
					}

					return (
						<div
							key={ header.id }
							className={ `iwbe-th${ canSort ? ' iwbe-th-sortable' : '' }${ sortClass }` }
							style={ { width, minWidth: width } }
							onClick={
								canSort
									? header.column.getToggleSortingHandler()
									: undefined
							}
						>
							{ header.isPlaceholder
								? null
								: flexRender(
										header.column.columnDef.header,
										header.getContext()
								  ) }
						</div>
					);
				} )
			) }
		</div>
	);
}
