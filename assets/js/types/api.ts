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
export const ProductSchema = z.object( {
	id: z.number(),
	name: z.string(),
	status: z.string(),
	sku: z.string(),
	regular_price: z.string(),
	sale_price: z.string(),
	stock_quantity: z.number().nullable(),
	post_modified: z.string(),
	thumbnail_id: z.number().nullable(),
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
