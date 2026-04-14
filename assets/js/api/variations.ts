import { apiFetch } from './client';
import { VariationsResponseSchema } from '@/types/api';
import type { VariationsResponse } from '@/types/api';

/**
 * Fetch all variations for a given variable product.
 *
 * GET /products/{id}/variations
 */
export function fetchVariations(
	productId: number,
	signal?: AbortSignal
): Promise< VariationsResponse > {
	return apiFetch(
		`products/${ productId }/variations`,
		VariationsResponseSchema,
		{ method: 'GET', signal }
	);
}
