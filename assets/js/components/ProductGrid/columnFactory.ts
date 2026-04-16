import type { ColumnDef } from '@tanstack/react-table';
import { __ } from '@wordpress/i18n';
import type { Field, FieldType, Product } from '@/types/api';

const COLUMN_WIDTHS: Record< FieldType, number > = {
	text: 200,
	textarea: 250,
	number: 100,
	price: 110,
	integer: 90,
	select: 120,
	boolean: 80,
	date: 130,
	taxonomy: 150,
	image: 80,
	gallery: 100,
	custom_meta: 150,
};

export const STATUS_LABELS: Record< string, string > = {
	publish: __( 'Published', 'ihumbak-woo-bulk-edit' ),
	draft: __( 'Draft', 'ihumbak-woo-bulk-edit' ),
	pending: __( 'Pending', 'ihumbak-woo-bulk-edit' ),
	private: __( 'Private', 'ihumbak-woo-bulk-edit' ),
	trash: __( 'Trash', 'ihumbak-woo-bulk-edit' ),
};

function formatPrice( value: string ): string {
	if ( ! value || value === '' ) {
		return '\u2014';
	}
	const num = parseFloat( value );
	if ( isNaN( num ) ) {
		return value;
	}
	return num.toFixed( 2 );
}

export function renderCellValue(
	value: unknown,
	field: Field
): string {
	if ( value === null || value === undefined || value === '' ) {
		return '\u2014';
	}

	switch ( field.type ) {
		case 'price':
			return formatPrice( String( value ) );

		case 'select': {
			const key = String( value );
			if ( field.key === 'status' ) {
				return STATUS_LABELS[ key ] ?? key;
			}
			return field.options[ key ] ?? key;
		}

		case 'boolean':
			return value
				? __( 'Yes', 'ihumbak-woo-bulk-edit' )
				: __( 'No', 'ihumbak-woo-bulk-edit' );

		case 'taxonomy':
			if ( Array.isArray( value ) ) {
				if ( value.length === 0 ) {
					return '\u2014';
				}
				return value
					.map( ( term: { name?: string } ) => term.name ?? '' )
					.filter( Boolean )
					.join( ', ' );
			}
			return String( value );

		case 'gallery':
			if ( Array.isArray( value ) ) {
				return value.length > 0
					? `${ value.length } ${ __( 'images', 'ihumbak-woo-bulk-edit' ) }`
					: '\u2014';
			}
			return '\u2014';

		case 'integer':
		case 'number':
			return String( value );

		default:
			if ( Array.isArray( value ) ) {
				return value.length > 0 ? value.join( ', ' ) : '\u2014';
			}
			return String( value );
	}
}

export interface FieldColumnMeta {
	field: Field;
}

export interface SelectionColumnMeta {
	isSelection: true;
}

export type GridColumnMeta = FieldColumnMeta | SelectionColumnMeta;

export function createColumns(
	fields: Field[]
): ColumnDef< Product, unknown >[] {
	const selectionColumn: ColumnDef< Product, unknown > = {
		id: 'select',
		size: 40,
		minSize: 40,
		maxSize: 40,
		enableSorting: false,
		enableHiding: false,
		enableResizing: false,
		meta: { isSelection: true } as SelectionColumnMeta,
	};

	const idColumn: ColumnDef< Product, unknown > = {
		id: 'id',
		accessorKey: 'id',
		header: __( 'ID', 'ihumbak-woo-bulk-edit' ),
		size: 60,
		minSize: 50,
		maxSize: 200,
		enableSorting: true,
		enableHiding: false,
		enableResizing: true,
	};

	const fieldColumns: ColumnDef< Product, unknown >[] = fields.map(
		( field ) => ( {
			id: field.key,
			accessorKey: field.key,
			header: field.label,
			size: COLUMN_WIDTHS[ field.type ] ?? 150,
			minSize: 50,
			maxSize: 600,
			enableSorting: field.sortable,
			enableHiding: true,
			enableResizing: true,
			meta: { field } as FieldColumnMeta,
		} )
	);

	return [ selectionColumn, idColumn, ...fieldColumns ];
}

export function getColumnWidths(
	columns: ColumnDef< Product, unknown >[]
): number[] {
	return columns.map( ( col ) => ( col.size as number ) ?? 150 );
}
