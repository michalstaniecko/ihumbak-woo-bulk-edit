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

// ── Variation ────────────────────────────────────────────────
export const VariationSchema = z.object( {
	id: z.number(),
	parent_id: z.number(),
	name: z.string(),
	sku: z.string(),
	regular_price: z.string(),
	sale_price: z.string(),
	stock_quantity: z.number().nullable(),
	manage_stock: z.boolean(),
	weight: z.string(),
	length: z.string(),
	width: z.string(),
	height: z.string(),
	thumbnail_id: z.number().nullable(),
	status: z.string(),
	menu_order: z.number(),
	attributes: z.record( z.string(), z.string() ),
	post_modified: z.string(),
} );
export type Variation = z.infer< typeof VariationSchema >;

export const VariationsResponseSchema = z.object( {
	parent_id: z.number(),
	items: z.array( VariationSchema ),
	total: z.number(),
} );
export type VariationsResponse = z.infer< typeof VariationsResponseSchema >;

// ── Product ──────────────────────────────────────────────────
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
	// Hydrated by the PHP backend in hydrateProducts(). Always present in
	// /products/query responses since Issue #15.
	type: z.string(),
	variations_count: z.number(),
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
	'<',
	'<=',
	'>',
	'>=',
	'IN',
	'NOT IN',
	'BETWEEN',
	'REGEXP',
] );
export type FilterOperator = z.infer< typeof FilterOperatorSchema >;

export const ProductFilterSchema = z.object( {
	field: z.string(),
	operator: FilterOperatorSchema,
	value: z.string().optional(),
} );
export type ProductFilter = z.infer< typeof ProductFilterSchema >;

// ── AND/OR Filter Tree (Issue #19) ────────────────────────────
export const FilterConditionSchema = z.object( {
	type: z.literal( 'condition' ),
	field: z.string(),
	operator: FilterOperatorSchema,
	value: z
		.union( [
			z.string(),
			z.array( z.string() ),
			z.tuple( [ z.string(), z.string() ] ),
		] )
		.optional(),
} );
export type FilterCondition = z.infer< typeof FilterConditionSchema >;

/**
 * FilterGroup — a recursive AND/OR group of conditions or nested groups.
 *
 * Defined as an interface (not z.infer) so z.lazy can reference it without
 * TypeScript circular-type inference issues.
 */
export interface FilterGroup {
	type: 'group';
	combinator: 'AND' | 'OR';
	children: FilterNode[];
}
export type FilterNode = FilterCondition | FilterGroup;

export const FilterGroupSchema: z.ZodType< FilterGroup > = z.lazy( () =>
	z.object( {
		type: z.literal( 'group' ),
		combinator: z.enum( [ 'AND', 'OR' ] ),
		children: z.array( z.union( [ FilterConditionSchema, FilterGroupSchema ] ) ),
	} )
);

/**
 * Convert a legacy flat ProductFilter array to a FilterGroup tree.
 * Useful when loading saved filter presets from the old format.
 */
export function legacyFiltersToGroup( filters: ProductFilter[] ): FilterGroup {
	return {
		type: 'group',
		combinator: 'AND',
		children: filters.map(
			( f ): FilterCondition => ( {
				type: 'condition',
				field: f.field,
				operator: f.operator,
				value: f.value,
			} )
		),
	};
}

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
	filters: z.union( [ z.array( ProductFilterSchema ), FilterGroupSchema ] ).default( [] ),
	ids: z.array( z.number().int() ).default( [] ).optional(),
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

// ── Saved Filters ────────────────────────────────────────────

/**
 * Explicit interface — bypasses Zod type-inference issues with z.lazy + union + default.
 * The `filters` field is always present after parsing (default = []).
 */
export interface SavedFilterDefinition {
	filters: ProductFilter[] | FilterGroup;
	search: string;
	sort?: Sort;
}

export const SavedFilterDefinitionSchema: z.ZodType< SavedFilterDefinition > = z.object( {
	filters: z
		.union( [ z.array( ProductFilterSchema ), FilterGroupSchema ] )
		.default( [] as ProductFilter[] ) as z.ZodType< ProductFilter[] | FilterGroup >,
	search: z.string().optional().default( '' ),
	sort: SortSchema.optional(),
} ) as z.ZodType< SavedFilterDefinition >;

export const SavedFilterSchema = z.object( {
	id: z.number(),
	user_id: z.number(),
	name: z.string(),
	definition: SavedFilterDefinitionSchema,
	is_shared: z.boolean(),
	created_at: z.string(),
	updated_at: z.string(),
} );
export type SavedFilter = z.infer< typeof SavedFilterSchema >;

export const SavedFiltersListResponseSchema = z.object( {
	items: z.array( SavedFilterSchema ),
} );
export type SavedFiltersListResponse = z.infer< typeof SavedFiltersListResponseSchema >;

export const CreateSavedFilterPayloadSchema = z.object( {
	name: z.string().min( 1 ).max( 191 ),
	definition: SavedFilterDefinitionSchema,
	is_shared: z.boolean().default( false ),
} );
export type CreateSavedFilterPayload = z.infer< typeof CreateSavedFilterPayloadSchema >;

export const UpdateSavedFilterPayloadSchema = z.object( {
	name: z.string().min( 1 ).max( 191 ).optional(),
	definition: SavedFilterDefinitionSchema.optional(),
	is_shared: z.boolean().optional(),
} );
export type UpdateSavedFilterPayload = z.infer< typeof UpdateSavedFilterPayloadSchema >;

export const DeleteSavedFilterResponseSchema = z.object( {
	deleted: z.boolean(),
	id: z.number(),
} );
export type DeleteSavedFilterResponse = z.infer< typeof DeleteSavedFilterResponseSchema >;

// ── Taxonomy Term Detail (Issue #45 — TaxonomyTermPicker) ────
export const TaxonomyTermDetailSchema = z.object( {
	id: z.number(),
	name: z.string(),
	slug: z.string(),
	count: z.number(),
	parent: z.number(),
} );
export type TaxonomyTermDetail = z.infer< typeof TaxonomyTermDetailSchema >;

export const TaxonomyTermsResponseSchema = z.object( {
	items: z.array( TaxonomyTermDetailSchema ),
	total: z.number(),
} );
export type TaxonomyTermsResponse = z.infer< typeof TaxonomyTermsResponseSchema >;

// ── Column Visibility Preferences (Issue #47) ─────────────────
export const ColumnVisibilityPreferenceSchema = z.object( {
	hidden: z.array( z.string() ),
	version: z.number().int(),
} );
export type ColumnVisibilityPreference = z.infer< typeof ColumnVisibilityPreferenceSchema >;

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
