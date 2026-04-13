import { z } from 'zod';

// ── Field Type enum (mirrors PHP FieldType enum) ─────────────
export const FieldTypeSchema = z.enum( [
	'text',
	'textarea',
	'number',
	'price',
	'integer',
	'select',
	'boolean',
	'date',
	'taxonomy',
	'image',
	'gallery',
	'custom_meta',
] );
export type FieldType = z.infer< typeof FieldTypeSchema >;

// ── Field ────────────────────────────────────────────────────
export const FieldSchema = z.object( {
	key: z.string(),
	label: z.string(),
	type: FieldTypeSchema,
	editable: z.boolean(),
	sortable: z.boolean(),
	filterable: z.boolean(),
	options: z.union( [
		z.record( z.string(), z.string() ),
		z.array( z.never() ),
	] ).transform( ( val ) => ( Array.isArray( val ) ? {} : val ) ),
} );
export type Field = z.infer< typeof FieldSchema >;

// GET /fields response
export const FieldsResponseSchema = z.array( FieldSchema );
export type FieldsResponse = z.infer< typeof FieldsResponseSchema >;

// ── Product ──────────────────────────────────────────────────
// ── Taxonomy term (used by categories, tags) ───────────────
export const TaxonomyTermSchema = z.object( {
	id: z.number(),
	name: z.string(),
} );
export type TaxonomyTerm = z.infer< typeof TaxonomyTermSchema >;

export const ProductSchema = z.object( {
	id: z.number(),
	name: z.string(),
	slug: z.string(),
	status: z.string(),
	description: z.string(),
	short_description: z.string(),
	menu_order: z.number(),
	date_created: z.string(),
	reviews_allowed: z.boolean(),
	sku: z.string(),
	regular_price: z.string(),
	sale_price: z.string(),
	manage_stock: z.boolean(),
	stock_quantity: z.number().nullable(),
	backorders: z.string(),
	sold_individually: z.boolean(),
	weight: z.string(),
	length: z.string(),
	width: z.string(),
	height: z.string(),
	virtual: z.boolean(),
	downloadable: z.boolean(),
	download_limit: z.number(),
	download_expiry: z.number(),
	purchase_note: z.string(),
	external_url: z.string(),
	button_text: z.string(),
	featured: z.boolean(),
	catalog_visibility: z.string(),
	thumbnail_id: z.number().nullable(),
	gallery: z.array( z.number() ),
	categories: z.array( TaxonomyTermSchema ),
	tags: z.array( TaxonomyTermSchema ),
	shipping_class: z.string(),
	cross_sells: z.array( z.number() ),
	upsells: z.array( z.number() ),
	post_modified: z.string(),
} );
export type Product = z.infer< typeof ProductSchema >;

// POST /products/query response
export const ProductsResponseSchema = z.object( {
	items: z.array( ProductSchema ),
	total: z.number(),
	page: z.number(),
	per_page: z.number(),
	pages: z.number(),
} );
export type ProductsResponse = z.infer< typeof ProductsResponseSchema >;

// ── Filter / Sort / Pagination (request params) ──────────────
export const FilterOperatorSchema = z.enum( [
	'=',
	'!=',
	'LIKE',
	'NOT LIKE',
	'IS EMPTY',
	'IS NOT EMPTY',
] );
export type FilterOperator = z.infer< typeof FilterOperatorSchema >;

export const ProductFilterSchema = z.object( {
	field: z.string(),
	operator: FilterOperatorSchema,
	value: z.string().optional(),
} );
export type ProductFilter = z.infer< typeof ProductFilterSchema >;

export const SortSchema = z.object( {
	field: z.string(),
	order: z.enum( [ 'asc', 'desc' ] ),
} );
export type Sort = z.infer< typeof SortSchema >;

export const PaginationSchema = z.object( {
	page: z.number().min( 1 ),
	per_page: z.number().min( 10 ).max( 500 ),
} );
export type Pagination = z.infer< typeof PaginationSchema >;

// POST /products/query request body
export const ProductsQueryParamsSchema = z.object( {
	filters: z.array( ProductFilterSchema ).default( [] ),
	sort: SortSchema.default( { field: 'name', order: 'asc' } ),
	page: z.number().min( 1 ).default( 1 ),
	per_page: z.number().min( 10 ).max( 500 ).default( 50 ),
} );
export type ProductsQueryParams = z.infer< typeof ProductsQueryParamsSchema >;

// ── Batch Save ──────────────────────────────────────────────
export const BatchSaveItemSchema = z.object( {
	id: z.number(),
	field: z.string(),
	value: z.unknown(),
	post_modified: z.string(),
} );
export type BatchSaveItem = z.infer< typeof BatchSaveItemSchema >;

export const BatchSaveResultSchema = z.object( {
	status: z.enum( [ 'success', 'error' ] ),
	id: z.number(),
	message: z.string().optional(),
	code: z.string().optional(),
} );
export type BatchSaveResult = z.infer< typeof BatchSaveResultSchema >;

export const BatchSaveResponseSchema = z.object( {
	results: z.array( BatchSaveResultSchema ),
	total: z.number(),
	success: z.number(),
	errors: z.number(),
} );
export type BatchSaveResponse = z.infer< typeof BatchSaveResponseSchema >;

// ── Bulk Delete ─────────────────────────────────────────────
export const BulkDeleteModeSchema = z.enum( [ 'trash', 'permanent' ] );
export type BulkDeleteMode = z.infer< typeof BulkDeleteModeSchema >;

export const BulkDeleteResultSchema = z.object( {
	status: z.enum( [ 'success', 'error' ] ),
	id: z.number(),
	message: z.string().optional(),
	code: z.string().optional(),
} );
export type BulkDeleteResult = z.infer< typeof BulkDeleteResultSchema >;

export const BulkDeleteResponseSchema = z.object( {
	results: z.array( BulkDeleteResultSchema ),
	total: z.number(),
	success: z.number(),
	errors: z.number(),
	variation_errors: z.number(),
	mode: BulkDeleteModeSchema,
} );
export type BulkDeleteResponse = z.infer< typeof BulkDeleteResponseSchema >;

// ── Bulk Duplicate ──────────────────────────────────────────
export const BulkDuplicateResultSchema = z.object( {
	status: z.enum( [ 'success', 'error' ] ),
	id: z.number(),
	new_id: z.number().optional(),
	message: z.string().optional(),
	code: z.string().optional(),
} );
export type BulkDuplicateResult = z.infer< typeof BulkDuplicateResultSchema >;

export const BulkDuplicateResponseSchema = z.object( {
	results: z.array( BulkDuplicateResultSchema ),
	total: z.number(),
	success: z.number(),
	errors: z.number(),
} );
export type BulkDuplicateResponse = z.infer< typeof BulkDuplicateResponseSchema >;

export interface BulkDuplicatePayload {
	ids: number[];
	copy_meta: boolean;
	copy_images: boolean;
}

// ── Changelog ────────────────────────────────────────────────
export const ChangelogEntrySchema = z.object( {
	id: z.number(),
	user_id: z.number(),
	user_name: z.string(),
	product_id: z.number(),
	product_name: z.string(),
	field: z.string(),
	old_value: z.unknown(),
	new_value: z.unknown(),
	changed_at: z.string(),
} );
export type ChangelogEntry = z.infer< typeof ChangelogEntrySchema >;

export const ChangelogResponseSchema = z.object( {
	items: z.array( ChangelogEntrySchema ),
	total: z.number(),
	page: z.number(),
	per_page: z.number(),
	pages: z.number(),
} );
export type ChangelogResponse = z.infer< typeof ChangelogResponseSchema >;

export interface ChangelogQueryParams {
	page?: number;
	per_page?: number;
	product_id?: number;
	user_id?: number;
	field?: string;
	date_from?: string;
	date_to?: string;
}

// ── API Error ────────────────────────────────────────────────
export const ApiErrorDataSchema = z
	.object( {
		status: z.number(),
	} )
	.passthrough();

export const ApiErrorSchema = z.object( {
	code: z.string(),
	message: z.string(),
	data: ApiErrorDataSchema,
} );
export type ApiErrorResponse = z.infer< typeof ApiErrorSchema >;
