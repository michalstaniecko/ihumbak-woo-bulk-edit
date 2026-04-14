import { useCallback } from 'react';
import type { Field } from '@/types/api';
import type { Variation } from '@/types/api';
import { useChangesStore, useEditingStore } from '@/store';
import { getEditorForField } from './editors';
import { validateCellValue } from './validation';
import { renderCellValue } from './columnFactory';

interface VariationRowProps {
	variation: Variation;
	fields: Field[];
	style?: React.CSSProperties;
	columnWidths: number[];
}

/**
 * Renders a single variation as an indented child row below its parent.
 *
 * Reuses the same editing / change-tracking infrastructure as `GridRow`:
 * variation IDs are unique WP post IDs so `useChangesStore` works unchanged.
 */
export function VariationRow( {
	variation,
	fields,
	style,
	columnWidths,
}: VariationRowProps ): JSX.Element {
	const variationId = variation.id;

	const activeCell = useEditingStore( ( s ) => s.activeCell );
	const focusedCell = useEditingStore( ( s ) => s.focusedCell );
	const validationError = useEditingStore( ( s ) => s.validationError );
	const startEditing = useEditingStore( ( s ) => s.startEditing );
	const stopEditing = useEditingStore( ( s ) => s.stopEditing );
	const setValidationError = useEditingStore( ( s ) => s.setValidationError );

	const getChangedValue = useChangesStore( ( s ) => s.getChangedValue );
	const setChange = useChangesStore( ( s ) => s.setChange );
	const changes = useChangesStore( ( s ) => s.changes );

	const variationChanges = changes[ String( variationId ) ] ?? {};

	/**
	 * Map of field key → current value (server value or pending change).
	 */
	const getVariationValue = useCallback(
		( fieldKey: string ): unknown => {
			const changedValue = getChangedValue( variationId, fieldKey );
			if ( changedValue !== undefined ) {
				return changedValue;
			}
			return ( variation as unknown as Record< string, unknown > )[ fieldKey ];
		},
		[ variation, variationId, getChangedValue ]
	);

	const handleConfirm = useCallback(
		( fieldKey: string, newValue: unknown, originalValue: unknown ) => {
			const field = fields.find( ( f ) => f.key === fieldKey );
			if ( ! field ) {
				stopEditing();
				return;
			}

			const pendingForVariation: Record< string, unknown > = {};
			for ( const [ key, change ] of Object.entries( variationChanges ) ) {
				pendingForVariation[ key ] = change.newValue;
			}
			pendingForVariation[ fieldKey ] = newValue;

			const result = validateCellValue(
				field,
				newValue,
				variation,
				pendingForVariation
			);

			if ( ! result.valid ) {
				setValidationError( result.message ?? null );
				return;
			}

			const oldValue =
				variationChanges[ fieldKey ]?.oldValue ?? originalValue;
			setChange( variationId, fieldKey, oldValue, newValue );
			stopEditing();
		},
		[
			variationId,
			fields,
			variationChanges,
			variation,
			setChange,
			stopEditing,
			setValidationError,
		]
	);

	const handleCancel = useCallback( () => {
		stopEditing();
	}, [ stopEditing ] );

	// Variation-visible field keys (subset of product fields that make sense
	// for variations — reuse the same editable fields list from `fields` prop
	// but skip fields that are parent-only such as name, slug, categories, etc.)
	const VARIATION_FIELD_KEYS = new Set( [
		'sku',
		'regular_price',
		'sale_price',
		'stock_quantity',
		'manage_stock',
		'weight',
		'length',
		'width',
		'height',
		'status',
	] );

	// The first column is always the checkbox / selection column — render an
	// indent placeholder there.  Subsequent columns map to fields by index.
	return (
		<div className="iwbe-row iwbe-row-variation" style={ style }>
			{ /* Indent / selection placeholder */ }
			<div
				className="iwbe-td iwbe-td-variation-indent"
				style={ { width: columnWidths[ 0 ] ?? 40, minWidth: columnWidths[ 0 ] ?? 40 } }
			/>

			{ fields.map( ( field, fieldIndex ) => {
				const width = columnWidths[ fieldIndex + 1 ] ?? 150;
				const fieldKey = field.key;

				if ( ! VARIATION_FIELD_KEYS.has( fieldKey ) ) {
					// Non-variation field — render empty cell.
					return (
						<div
							key={ fieldKey }
							className="iwbe-td iwbe-td-variation-empty"
							style={ { width, minWidth: width } }
						/>
					);
				}

				const serverValue = getVariationValue( fieldKey );
				const changedValue = getChangedValue( variationId, fieldKey );
				const hasChange = changedValue !== undefined;
				const displayValue = hasChange ? changedValue : serverValue;

				const isActive =
					activeCell !== null &&
					activeCell.productId === variationId &&
					activeCell.fieldKey === fieldKey;
				const isFocused =
					focusedCell !== null &&
					focusedCell.productId === variationId &&
					focusedCell.fieldKey === fieldKey;

				if ( isActive && field.editable ) {
					const Editor = getEditorForField( field.type );
					if ( Editor ) {
						return (
							<div
								key={ fieldKey }
								className={ `iwbe-td${ hasChange ? ' iwbe-td-changed' : '' }` }
								style={ {
									width,
									minWidth: width,
									padding: 0,
									position: 'relative',
								} }
								onClick={ ( e ) => e.stopPropagation() }
							>
								<Editor
									value={ displayValue }
									field={ field }
									productId={ variationId }
									onConfirm={ ( val ) =>
										handleConfirm( fieldKey, val, serverValue )
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

				const formatted = renderCellValue( displayValue, field );

				return (
					<div
						key={ fieldKey }
						className={ cellClasses }
						style={ { width, minWidth: width } }
						title={ formatted }
						onClick={ ( e ) => {
							if ( field.editable ) {
								e.stopPropagation();
								startEditing( variationId, fieldKey );
							}
						} }
					>
						{ formatted }
					</div>
				);
			} ) }
		</div>
	);
}
