import { useEffect, useMemo } from 'react';
import type { Table } from '@tanstack/react-table';
import type { Product, Field } from '@/types/api';
import { useEditingStore, useChangesStore } from '@/store';
import type { FieldColumnMeta } from '@/components/ProductGrid/columnFactory';

function isFieldMeta( meta: unknown ): meta is FieldColumnMeta {
	return (
		meta !== null &&
		meta !== undefined &&
		typeof meta === 'object' &&
		'field' in meta
	);
}

export function useGridKeyboardNav(
	table: Table< Product >,
	fields: Field[]
): void {
	const editableFieldKeys = useMemo(
		() => fields.filter( ( f ) => f.editable ).map( ( f ) => f.key ),
		[ fields ]
	);

	useEffect( () => {
		function handleKeyDown( event: KeyboardEvent ): void {
			const target = event.target as HTMLElement;

			// Only handle keys when inside our app
			if ( ! target.closest( '.iwbe-app' ) ) {
				return;
			}

			const {
				activeCell,
				focusedCell,
				startEditing,
				stopEditing,
				setFocusedCell,
				clearFocus,
			} = useEditingStore.getState();

			const rows = table.getRowModel().rows;
			if ( rows.length === 0 || editableFieldKeys.length === 0 ) {
				return;
			}

			// When a cell is actively being edited, let the editor handle most keys
			if ( activeCell !== null ) {
				if ( event.key === 'Escape' ) {
					event.preventDefault();
					stopEditing();
					return;
				}
				// Tab navigation while editing — handled by editors themselves
				// which call onConfirm, then we move focus
				return;
			}

			// No focused cell — nothing to do
			if ( focusedCell === null ) {
				return;
			}

			const currentRowIndex = rows.findIndex(
				( r ) => r.original.id === focusedCell.productId
			);
			const currentFieldIndex = editableFieldKeys.indexOf(
				focusedCell.fieldKey
			);

			if ( currentRowIndex === -1 || currentFieldIndex === -1 ) {
				return;
			}

			switch ( event.key ) {
				case 'ArrowUp': {
					event.preventDefault();
					if ( currentRowIndex > 0 ) {
						const prevRow = rows[ currentRowIndex - 1 ];
						setFocusedCell(
							prevRow.original.id,
							focusedCell.fieldKey
						);
					}
					break;
				}

				case 'ArrowDown': {
					event.preventDefault();
					if ( currentRowIndex < rows.length - 1 ) {
						const nextRow = rows[ currentRowIndex + 1 ];
						setFocusedCell(
							nextRow.original.id,
							focusedCell.fieldKey
						);
					}
					break;
				}

				case 'ArrowLeft': {
					event.preventDefault();
					if ( currentFieldIndex > 0 ) {
						setFocusedCell(
							focusedCell.productId,
							editableFieldKeys[ currentFieldIndex - 1 ]
						);
					}
					break;
				}

				case 'ArrowRight': {
					event.preventDefault();
					if (
						currentFieldIndex <
						editableFieldKeys.length - 1
					) {
						setFocusedCell(
							focusedCell.productId,
							editableFieldKeys[ currentFieldIndex + 1 ]
						);
					}
					break;
				}

				case 'Enter': {
					event.preventDefault();
					startEditing(
						focusedCell.productId,
						focusedCell.fieldKey
					);
					break;
				}

				case 'Tab': {
					event.preventDefault();
					const direction = event.shiftKey ? -1 : 1;
					let nextFieldIdx = currentFieldIndex + direction;
					let nextRowIdx = currentRowIndex;

					if ( nextFieldIdx >= editableFieldKeys.length ) {
						nextFieldIdx = 0;
						nextRowIdx += 1;
					} else if ( nextFieldIdx < 0 ) {
						nextFieldIdx = editableFieldKeys.length - 1;
						nextRowIdx -= 1;
					}

					if ( nextRowIdx >= 0 && nextRowIdx < rows.length ) {
						setFocusedCell(
							rows[ nextRowIdx ].original.id,
							editableFieldKeys[ nextFieldIdx ]
						);
					}
					break;
				}

				case 'Escape': {
					event.preventDefault();
					clearFocus();
					break;
				}
			}
		}

		document.addEventListener( 'keydown', handleKeyDown );
		return () => {
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ table, editableFieldKeys ] );
}
