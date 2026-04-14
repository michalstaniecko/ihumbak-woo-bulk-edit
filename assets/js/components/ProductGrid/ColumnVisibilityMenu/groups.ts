import { __ } from '@wordpress/i18n';

/**
 * Ordered column group definitions.
 *
 * Each group has a translated label and an ordered list of field keys that
 * belong to it. Any field key not listed here will appear under "Other".
 */
export interface ColumnGroup {
	label: string;
	keys: ReadonlyArray< string >;
}

export function getColumnGroups(): ColumnGroup[] {
	return [
		{
			label: __( 'Product Info', 'ihumbak-woo-bulk-edit' ),
			keys: [
				'name',
				'slug',
				'sku',
				'status',
				'catalog_visibility',
				'featured',
				'date_created',
			],
		},
		{
			label: __( 'Descriptions', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'description', 'short_description', 'purchase_note' ],
		},
		{
			label: __( 'Pricing', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'regular_price', 'sale_price' ],
		},
		{
			label: __( 'Stock', 'ihumbak-woo-bulk-edit' ),
			keys: [
				'manage_stock',
				'stock_quantity',
				'backorders',
				'sold_individually',
			],
		},
		{
			label: __( 'Shipping & Dimensions', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'weight', 'length', 'width', 'height', 'shipping_class' ],
		},
		{
			label: __( 'Downloadable / Virtual', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'virtual', 'downloadable', 'download_limit', 'download_expiry' ],
		},
		{
			label: __( 'External Product', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'external_url', 'button_text' ],
		},
		{
			label: __( 'Categories & Tags', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'categories', 'tags' ],
		},
		{
			label: __( 'Media', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'thumbnail_id', 'gallery' ],
		},
		{
			label: __( 'Linked Products', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'cross_sells', 'upsells' ],
		},
		{
			label: __( 'Other', 'ihumbak-woo-bulk-edit' ),
			keys: [ 'reviews_allowed', 'menu_order' ],
		},
	];
}

/**
 * Build a map from field key → group label for quick lookup.
 */
export function buildGroupMap( groups: ColumnGroup[] ): Map< string, string > {
	const map = new Map< string, string >();
	for ( const group of groups ) {
		for ( const key of group.keys ) {
			map.set( key, group.label );
		}
	}
	return map;
}
