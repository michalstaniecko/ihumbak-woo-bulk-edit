import { useCallback } from 'react';
import type { Row } from '@tanstack/react-table';
import type { Product } from '@/types/api';
import type { SelectionColumnMeta, FieldColumnMeta } from './columnFactory';
import { renderCellValue } from './columnFactory';
import { useChangesStore, useEditingStore, useExpansionStore } from '@/store';
import { getEditorForField } from './editors';
import { validateCellValue } from './validation';
import { ExpansionToggle } from './ExpansionToggle';

interface GridRowProps {
	row: Row< Product >;
	style?: React.CSSProperties;
	onRowClick: ( row: Row< Product >, event: React.MouseEvent ) => void;
	columnWidths: number[];
	isExpanded?: boolean;
	isLoadingVariations?: boolean;
}

function isSelectionMeta( meta: unknown ): meta is SelectionColumnMeta {
	return (
		meta !== null &&
		meta !== undefined &&
		typeof meta === 'object' &&
		'isSelection' in meta
	);
}

function isFieldMeta( meta: unknown ): meta is FieldColumnMeta {
	return (
		meta !== null &&
		meta !== undefined &&
		typeof meta === 'object' &&
		'field' in meta
	);
}

export function GridRow( {
	row,
	style,
	onRowClick,
	columnWidths,
	isExpanded = false,
	isLoadingVariations = false,
}: GridRowProps ): JSX.Element {
	const isSelected = row.getIsSelected();
	const productId = row.original.id;
	const isVariable = row.original.type === 'variable';

	const toggleExpansion = useExpansionStore( ( s ) => s.toggle );

	const activeCell = useEditingStore( ( s ) => s.activeCell );
	const focusedCell = useEditingStore( ( s ) => s.focusedCell );
	const validationError = useEditingStore( ( s ) => s.validationError );
	const startEditing = useEditingStore( ( s ) => s.startEditing );
	const stopEditing = useEditingStore( ( s ) => s.stopEditing );
	const setValidationError = useEditingStore( ( s ) => s.setValidationError );

	const getChangedValue = useChangesStore( ( s ) => s.getChangedValue );
	const setChange = useChangesStore( ( s ) => s.setChange );
	const changes = useChangesStore( ( s ) => s.changes );

	const productChanges = changes[ String( productId ) ] ?? {};

	const handleConfirm = useCallback(
		( fieldKey: string, newValue: unknown, originalValue: unknown ) => {
			const field = row
				.getVisibleCells()
				.find( ( c ) => {
					const m = c.column.columnDef.meta;
					return isFieldMeta( m ) && m.field.key === fieldKey;
				} );

			const meta = field?.column.columnDef.meta;
			if ( ! meta || ! isFieldMeta( meta ) ) {
				stopEditing();
				return;
			}

			const pendingForProduct: Record< string, unknown > = {};
			for ( const [ key, change ] of Object.entries( productChanges ) ) {
				pendingForProduct[ key ] = change.newValue;
			}
			pendingForProduct[ fieldKey ] = newValue;

			const result = validateCellValue(
				meta.field,
				newValue,
				row.original,
				pendingForProduct
			);

			if ( ! result.valid ) {
				setValidationError( result.message ?? null );
				return;
			}

			const oldValue =
				productChanges[ fieldKey ]?.oldValue ?? originalValue;
			setChange( productId, fieldKey, oldValue, newValue );
			stopEditing();
		},
		[ productId, row, productChanges, setChange, stopEditing, setValidationError ]
	);

	const handleCancel = useCallback( () => {
		stopEditing();
	}, [ stopEditing ] );

	return (
		<div
			className={ `iwbe-row${ isSelected ? ' iwbe-row-selected' : '' }` }
			style={ style }
			onClick={ ( event ) => onRowClick( row, event ) }
		>
			{ row.getVisibleCells().map( ( cell, cellIndex ) => {
				const meta = cell.column.columnDef.meta;
				const width = columnWidths[ cellIndex ] ?? 150;

				if ( isSelectionMeta( meta ) ) {
					return (
						<div
							key={ cell.id }
							className="iwbe-td iwbe-td-select"
							style={ { width, minWidth: width } }
						>
							<input
								type="checkbox"
								checked={ row.getIsSelected() }
								onChange={ row.getToggleSelectedHandler() }
								onClick={ ( e ) => e.stopPropagation() }
							/>
							{ isVariable && (
								<ExpansionToggle
									isExpanded={ isExpanded }
									isLoading={ isLoadingVariations }
									variationsCount={
										row.original.variations_count
									}
									onToggle={ () =>
										toggleExpansion( productId )
									}
								/>
							) }
						</div>
					);
				}

				const serverValue = cell.getValue();

				if ( isFieldMeta( meta ) ) {
					const fieldKey = meta.field.key;
					const changedValue = getChangedValue( productId, fieldKey );
					const hasChange = changedValue !== undefined;
					const displayValue = hasChange ? changedValue : serverValue;

					const isActive =
						activeCell !== null &&
						activeCell.productId === productId &&
						activeCell.fieldKey === fieldKey;
					const isFocused =
						focusedCell !== null &&
						focusedCell.productId === productId &&
						focusedCell.fieldKey === fieldKey;

					if ( isActive && meta.field.editable ) {
						const Editor = getEditorForField( meta.field.type );
						if ( Editor ) {
							return (
								<div
									key={ cell.id }
									className={ `iwbe-td${ hasChange ? ' iwbe-td-changed' : '' }` }
									style={ { width, minWidth: width, padding: 0, position: 'relative' } }
									onClick={ ( e ) => e.stopPropagation() }
								>
									<Editor
										value={ displayValue }
										field={ meta.field }
										productId={ productId }
										onConfirm={ ( val ) =>
											handleConfirm(
												fieldKey,
												val,
												serverValue
											)
										}
										onCancel={ handleCancel }
										validationError={ validationError }
										width={ width }
									/>
								</div>
							);
						}
					}

					const cellClasses = [
						'iwbe-td',
						hasChange ? 'iwbe-td-changed' : '',
						isFocused ? 'iwbe-td-focused' : '',
					]
						.filter( Boolean )
						.join( ' ' );

					const formatted = renderCellValue( displayValue, meta.field );

					return (
						<div
							key={ cell.id }
							className={ cellClasses }
							style={ { width, minWidth: width } }
							title={ formatted }
							onClick={ ( e ) => {
								if ( meta.field.editable ) {
									e.stopPropagation();
									startEditing( productId, fieldKey );
								}
							} }
						>
							{ formatted }
						</div>
					);
				}

				const displayValue =
					serverValue !== null && serverValue !== undefined
						? String( serverValue )
						: '\u2014';

				return (
					<div
						key={ cell.id }
						className="iwbe-td"
						style={ { width, minWidth: width } }
						title={ displayValue }
					>
						{ displayValue }
					</div>
				);
			} ) }
		</div>
	);
}
