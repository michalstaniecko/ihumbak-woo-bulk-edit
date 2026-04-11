import type { Row } from '@tanstack/react-table';
import type { Product } from '@/types/api';
import type { SelectionColumnMeta, FieldColumnMeta } from './columnFactory';
import { renderCellValue } from './columnFactory';

interface GridRowProps {
	row: Row< Product >;
	style?: React.CSSProperties;
	onRowClick: ( row: Row< Product >, event: React.MouseEvent ) => void;
	columnWidths: number[];
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
}: GridRowProps ): JSX.Element {
	const isSelected = row.getIsSelected();

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
						</div>
					);
				}

				const value = cell.getValue();
				let displayValue: string;

				if ( isFieldMeta( meta ) ) {
					displayValue = renderCellValue( value, meta.field );
				} else {
					displayValue =
						value !== null && value !== undefined
							? String( value )
							: '\u2014';
				}

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
