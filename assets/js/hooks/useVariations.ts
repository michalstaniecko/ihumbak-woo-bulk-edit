import { useQueries } from '@tanstack/react-query';
import { fetchVariations } from '@/api/variations';
import type { VariationsResponse } from '@/types/api';

/**
 * Fetches variations for multiple variable products in parallel.
 *
 * Uses `useQueries` (not a loop of `useQuery`) so the hook count stays
 * stable regardless of how many products are expanded.
 *
 * @param expandedIds Sorted list of parent product IDs currently expanded.
 * @returns Map of parentId → VariationsResponse (only for resolved queries).
 */
export function useVariations(
	expandedIds: number[]
): Map< number, VariationsResponse > {
	const queries = useQueries( {
		queries: expandedIds.map( ( id ) => ( {
			queryKey: [ 'variations', id ] as const,
			queryFn: ( { signal }: { signal?: AbortSignal } ) =>
				fetchVariations( id, signal ),
			staleTime: 30_000,
		} ) ),
	} );

	const map = new Map< number, VariationsResponse >();

	for ( let i = 0; i < expandedIds.length; i++ ) {
		const result = queries[ i ];
		if ( result.data ) {
			map.set( expandedIds[ i ], result.data );
		}
	}

	return map;
}
