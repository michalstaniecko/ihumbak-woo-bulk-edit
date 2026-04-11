import { apiFetch } from './client';
import { ProductsResponseSchema, BatchSaveResponseSchema } from '@/types/api';
import type {
	ProductsResponse,
	ProductsQueryParams,
	BatchSaveItem,
	BatchSaveResponse,
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
