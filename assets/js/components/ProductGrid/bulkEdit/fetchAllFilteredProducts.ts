import { fetchProducts } from '@/api/products';
import type { Product, ProductFilter, Sort } from '@/types/api';

interface FetchAllParams {
	filters: ProductFilter[];
	sort: Sort;
	chunkSize?: number;
	maxProducts?: number;
	signal?: AbortSignal;
	onProgress?: ( loaded: number, total: number ) => void;
}

/**
 * Iteratively page through /products/query to load every product that matches
 * the current filter set. Uses the backend's max per_page (500) by default.
 * @param root0
 * @param root0.filters
 * @param root0.sort
 * @param root0.chunkSize
 * @param root0.maxProducts
 * @param root0.signal
 * @param root0.onProgress
 */
export async function fetchAllFilteredProducts( {
	filters,
	sort,
	chunkSize = 500,
	maxProducts = 5000,
	signal,
	onProgress,
}: FetchAllParams ): Promise< Product[] > {
	const collected: Product[] = [];
	let page = 1;
	let totalPages = 1;
	let total = 0;

	do {
		const response = await fetchProducts(
			{
				filters,
				sort,
				page,
				per_page: chunkSize,
			},
			signal
		);

		collected.push( ...response.items );
		totalPages = response.pages;
		total = response.total;

		onProgress?.( collected.length, total );

		if ( collected.length >= maxProducts ) {
			break;
		}

		page += 1;
	} while ( page <= totalPages );

	return collected;
}
