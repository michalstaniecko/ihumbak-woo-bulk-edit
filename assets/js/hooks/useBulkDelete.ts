import { useCallback } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { bulkDeleteProducts } from '@/api/products';
import type { BulkDeleteMode, BulkDeleteResponse } from '@/types/api';

export interface UseBulkDeleteReturn {
	isDeleting: boolean;
	lastResult: BulkDeleteResponse | undefined;
	error: Error | null;
	deleteProducts: (
		ids: number[],
		mode: BulkDeleteMode
	) => Promise< BulkDeleteResponse >;
	reset: () => void;
}

/**
 * React Query mutation hook for bulk-deleting products via
 * DELETE /products/batch.
 *
 * On success, invalidates the products and changelog query caches so the grid
 * refetches and the audit log reflects the new entries.
 */
export function useBulkDelete(): UseBulkDeleteReturn {
	const queryClient = useQueryClient();

	const mutation = useMutation< BulkDeleteResponse,
		Error,
		{ ids: number[]; mode: BulkDeleteMode }
	>( {
		mutationFn: ( { ids, mode } ) => bulkDeleteProducts( { ids, mode } ),
		onSuccess: async () => {
			await queryClient.invalidateQueries( { queryKey: [ 'products' ] } );
			await queryClient.invalidateQueries( { queryKey: [ 'changelog' ] } );
		},
	} );

	const deleteProducts = useCallback(
		(
			ids: number[],
			mode: BulkDeleteMode
		): Promise< BulkDeleteResponse > => {
			return mutation.mutateAsync( { ids, mode } );
		},
		[ mutation ]
	);

	return {
		isDeleting: mutation.isPending,
		lastResult: mutation.data,
		error: mutation.error ?? null,
		deleteProducts,
		reset: mutation.reset,
	};
}
