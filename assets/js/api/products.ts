import { apiFetch } from './client';
import { ProductsResponseSchema } from '@/types/api';
import type { ProductsResponse, ProductsQueryParams } from '@/types/api';

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
