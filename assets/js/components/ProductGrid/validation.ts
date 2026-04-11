import { __, sprintf } from '@wordpress/i18n';
import type { Field, Product } from '@/types/api';

export interface ValidationResult {
	valid: boolean;
	message?: string;
}

export function validateCellValue(
	field: Field,
	newValue: unknown,
	currentProduct: Product,
	pendingChanges: Record< string, unknown >
): ValidationResult {
	const strVal = String( newValue ?? '' ).trim();

	if ( strVal === '' ) {
		return { valid: true };
	}

	switch ( field.type ) {
		case 'price':
		case 'number': {
			const num = parseFloat( strVal );
			if ( isNaN( num ) ) {
				return {
					valid: false,
					message: __( 'Must be a valid number', 'ihumbak-woo-bulk-edit' ),
				};
			}
			if ( field.type === 'price' && num < 0 ) {
				return {
					valid: false,
					message: __( 'Price cannot be negative', 'ihumbak-woo-bulk-edit' ),
				};
			}

			if ( field.key === 'sale_price' && strVal !== '' ) {
				const regularPriceRaw =
					pendingChanges.regular_price !== undefined
						? pendingChanges.regular_price
						: currentProduct.regular_price;
				const regularPrice = parseFloat( String( regularPriceRaw ?? '' ) );
				if ( ! isNaN( regularPrice ) && num >= regularPrice ) {
					return {
						valid: false,
						message: sprintf(
							/* translators: %s: regular price value */
							__(
								'Sale price must be less than regular price (%s)',
								'ihumbak-woo-bulk-edit'
							),
							regularPrice.toFixed( 2 )
						),
					};
				}
			}

			return { valid: true };
		}

		case 'integer': {
			const num = parseInt( strVal, 10 );
			if ( isNaN( num ) || String( num ) !== strVal ) {
				return {
					valid: false,
					message: __( 'Must be a whole number', 'ihumbak-woo-bulk-edit' ),
				};
			}
			if ( field.key === 'stock_quantity' && num < 0 ) {
				return {
					valid: false,
					message: __(
						'Stock quantity cannot be negative',
						'ihumbak-woo-bulk-edit'
					),
				};
			}
			return { valid: true };
		}

		default:
			return { valid: true };
	}
}
