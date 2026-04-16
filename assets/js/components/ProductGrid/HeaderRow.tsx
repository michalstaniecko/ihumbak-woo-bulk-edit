import { useEffect, useRef, useState } from 'react';
import { flexRender } from '@tanstack/react-table';
import type { Table } from '@tanstack/react-table';
import { __ } from '@wordpress/i18n';
import type { Field, Product } from '@/types/api';
import type { FieldColumnMeta, SelectionColumnMeta } from './columnFactory';
import { inferBulkOperationKind } from './bulkOperations';
import { ColumnResizeHandle } from './ColumnResizeHandle';

interface HeaderRowProps {
	table: Table< Product >;
	columnWidths: number[];
	onBulkEdit?: ( field: Field ) => void;
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

function isFieldColumn( meta: unknown ): meta is FieldColumnMeta {
	return (
		meta !== null &&
		meta !== undefined &&
		typeof meta === 'object' &&
		'field' in meta
	);
}

interface HeaderCellMenuProps {
	field: Field;
	onSort: ( order: 'asc' | 'desc' ) => void;
	onBulkEdit: () => void;
	sortable: boolean;
	onClose: () => void;
}

function HeaderCellMenu( {
	field,
	onSort,
	onBulkEdit,
	sortable,
	onClose,
}: HeaderCellMenuProps ): JSX.Element {
	const ref = useRef< HTMLDivElement | null >( null );

	useEffect( () => {
		const handleClickOutside = ( e: MouseEvent ): void => {
			if (
				ref.current &&
				! ref.current.contains( e.target as Node )
			) {
				onClose();
			}
		};
		const handleKey = ( e: KeyboardEvent ): void => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'mousedown', handleClickOutside );
		document.addEventListener( 'keydown', handleKey );
		return () => {
			document.removeEventListener( 'mousedown', handleClickOutside );
			document.removeEventListener( 'keydown', handleKey );
		};
	}, [ onClose ] );

	const bulkSupported = inferBulkOperationKind( field ) !== null;

	return (
		<div
			ref={ ref }
			className="iwbe-header-menu"
			onClick={ ( e ) => e.stopPropagation() }
		>
			{ sortable && (
				<>
					<button
						type="button"
						className="iwbe-header-menu-item"
						onClick={ () => {
							onSort( 'asc' );
							onClose();
						} }
					>
						{ __( 'Sort ascending', 'ihumbak-woo-bulk-edit' ) }
					</button>
					<button
						type="button"
						className="iwbe-header-menu-item"
						onClick={ () => {
							onSort( 'desc' );
							onClose();
						} }
					>
						{ __( 'Sort descending', 'ihumbak-woo-bulk-edit' ) }
					</button>
					<div className="iwbe-header-menu-separator" />
				</>
			) }
			<button
				type="button"
				className="iwbe-header-menu-item"
				disabled={ ! bulkSupported }
				onClick={ () => {
					onBulkEdit();
					onClose();
				} }
			>
				{ __( 'Bulk edit this column', 'ihumbak-woo-bulk-edit' ) }
			</button>
		</div>
	);
}

/** Column IDs that cannot be dragged or used as drop targets for reordering. */
const PINNED_COLUMN_IDS = new Set< string >( [ 'select', 'id' ] );

function reorderColumns(
	currentOrder: string[],
	draggedId: string,
	targetId: string
): string[] {
	const without = currentOrder.filter( ( id ) => id !== draggedId );
	const targetIndex = without.indexOf( targetId );
	if ( targetIndex === -1 ) return currentOrder;
	without.splice( targetIndex, 0, draggedId );
	return without;
}

export function HeaderRow( {
	table,
	columnWidths,
	onBulkEdit,
}: HeaderRowProps ): JSX.Element {
	const [ openMenu, setOpenMenu ] = useState< string | null >( null );
	const [ draggedColumnId, setDraggedColumnId ] = useState< string | null >( null );
	const [ dragOverColumnId, setDragOverColumnId ] = useState< string | null >( null );

	return (
		<div className="iwbe-header-row">
			{ table.getHeaderGroups().map( ( headerGroup ) =>
				headerGroup.headers.map( ( header, headerIndex ) => {
					const canSort = header.column.getCanSort();
					const sorted = header.column.getIsSorted();
					const meta = header.column.columnDef.meta;
					const width = columnWidths[ headerIndex ] ?? 150;
					const isPinned = PINNED_COLUMN_IDS.has( header.column.id );
					const canResize = header.column.getCanResize();
					const isResizing = header.column.getIsResizing();
					const isDraggingThis = draggedColumnId === header.column.id;
					const isDragOverThis = dragOverColumnId === header.column.id;

					let sortClass = '';
					if ( sorted === 'asc' ) {
						sortClass = ' iwbe-sort-asc';
					} else if ( sorted === 'desc' ) {
						sortClass = ' iwbe-sort-desc';
					}

					const resizingClass = isResizing ? ' iwbe-th--resizing' : '';
					const draggingClass = isDraggingThis ? ' iwbe-th--dragging' : '';
					const dragOverClass = isDragOverThis ? ' iwbe-th--drag-over' : '';

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

					const field = isFieldColumn( meta ) ? meta.field : null;
					const canBulkEdit =
						field !== null && onBulkEdit !== undefined;
					const isMenuOpen = openMenu === header.id;

					return (
						<div
							key={ header.id }
							className={ `iwbe-th${ canSort ? ' iwbe-th-sortable' : '' }${ sortClass }${ resizingClass }${ draggingClass }${ dragOverClass }` }
							style={ { width, minWidth: width } }
							draggable={ ! isPinned }
							onDragStart={ ! isPinned ? ( e: React.DragEvent< HTMLDivElement > ) => {
								e.dataTransfer.setData( 'text/plain', header.column.id );
								e.dataTransfer.effectAllowed = 'move';
								setDraggedColumnId( header.column.id );
							} : undefined }
							onDragOver={ ! isPinned ? ( e: React.DragEvent< HTMLDivElement > ) => {
								if ( draggedColumnId && draggedColumnId !== header.column.id ) {
									e.preventDefault();
									e.dataTransfer.dropEffect = 'move';
									setDragOverColumnId( header.column.id );
								}
							} : undefined }
							onDragLeave={ ! isPinned ? () => {
								setDragOverColumnId( null );
							} : undefined }
							onDrop={ ! isPinned ? ( e: React.DragEvent< HTMLDivElement > ) => {
								e.preventDefault();
								const sourceId = e.dataTransfer.getData( 'text/plain' );
								if ( sourceId && sourceId !== header.column.id && ! PINNED_COLUMN_IDS.has( sourceId ) ) {
									const currentOrder = table.getAllLeafColumns().map( ( c ) => c.id );
									const newOrder = reorderColumns( currentOrder, sourceId, header.column.id );
									table.setColumnOrder( newOrder );
								}
								setDraggedColumnId( null );
								setDragOverColumnId( null );
							} : undefined }
							onDragEnd={ ! isPinned ? () => {
								setDraggedColumnId( null );
								setDragOverColumnId( null );
							} : undefined }
						>
							<span
								className="iwbe-th-label"
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
							</span>
							{ canBulkEdit && (
								<div className="iwbe-th-menu-wrap">
									<button
										type="button"
										className="iwbe-th-menu-btn"
										aria-label={ __(
											'Column options',
											'ihumbak-woo-bulk-edit'
										) }
										aria-haspopup="menu"
										aria-expanded={ isMenuOpen }
										onClick={ ( e ) => {
											e.stopPropagation();
											setOpenMenu(
												isMenuOpen ? null : header.id
											);
										} }
									>
										▾
									</button>
									{ isMenuOpen && field && (
										<HeaderCellMenu
											field={ field }
											sortable={ canSort }
											onSort={ ( order ) => {
												header.column.toggleSorting(
													order === 'desc'
												);
											} }
											onBulkEdit={ () =>
												onBulkEdit( field )
											}
											onClose={ () => setOpenMenu( null ) }
										/>
									) }
								</div>
							) }
							{ canResize && (
								<ColumnResizeHandle header={ header } />
							) }
						</div>
					);
				} )
			) }
		</div>
	);
}
