import { apiFetch } from './client';
import {
	ProductsResponseSchema,
	BatchSaveResponseSchema,
	BulkDeleteResponseSchema,
} from '@/types/api';
import type {
	ProductsResponse,
	ProductsQueryParams,
	BatchSaveItem,
	BatchSaveResponse,
	BulkDeleteMode,
	BulkDeleteResponse,
} from '@/types/api';

export function fetchProducts(
	params: ProductsQueryParams,
	signal?: AbortSignal
): Promise< ProductsResponse > {
	return apiFetch( 'products/query', ProductsResponseSchema, {
		method: 'POST',
		body: params,
		signal,
	} );
}

export function batchSave(
	changes: BatchSaveItem[],
	signal?: AbortSignal
): Promise< BatchSaveResponse > {
	return apiFetch( 'products/batch', BatchSaveResponseSchema, {
		method: 'PUT',
		body: { changes },
		signal,
	} );
}

export interface BulkDeletePayload {
	ids: number[];
	mode: BulkDeleteMode;
}

export function bulkDeleteProducts(
	payload: BulkDeletePayload,
	signal?: AbortSignal
): Promise< BulkDeleteResponse > {
	return apiFetch( 'products/batch', BulkDeleteResponseSchema, {
		method: 'DELETE',
		body: payload,
		signal,
	} );
}
