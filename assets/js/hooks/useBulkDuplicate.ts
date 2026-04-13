import { useCallback } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { bulkDuplicateProducts } from '@/api/products';
import type {
	BulkDuplicatePayload,
	BulkDuplicateResponse,
} from '@/types/api';

export interface UseBulkDuplicateReturn {
	isDuplicating: boolean;
	lastResult: BulkDuplicateResponse | undefined;
	error: Error | null;
	duplicateProducts: (
		ids: number[],
		copyMeta: boolean,
		copyImages: boolean
	) => Promise< BulkDuplicateResponse >;
	reset: () => void;
}

/**
 * React Query mutation hook for bulk-duplicating products via
 * POST /products/duplicate.
 *
 * On success, invalidates the products and changelog query caches so the grid
 * refetches (the new drafts will appear when filters allow them) and the audit
 * log shows the new entries.
 */
export function useBulkDuplicate(): UseBulkDuplicateReturn {
	const queryClient = useQueryClient();

	const mutation = useMutation<
		BulkDuplicateResponse,
		Error,
		BulkDuplicatePayload
	>( {
		mutationFn: ( payload ) => bulkDuplicateProducts( payload ),
		onSuccess: async () => {
			await queryClient.invalidateQueries( { queryKey: [ 'products' ] } );
			await queryClient.invalidateQueries( { queryKey: [ 'changelog' ] } );
		},
	} );

	const duplicateProducts = useCallback(
		(
			ids: number[],
			copyMeta: boolean,
			copyImages: boolean
		): Promise< BulkDuplicateResponse > => {
			return mutation.mutateAsync( {
				ids,
				copy_meta: copyMeta,
				copy_images: copyImages,
			} );
		},
		[ mutation ]
	);

	return {
		isDuplicating: mutation.isPending,
		lastResult: mutation.data,
		error: mutation.error ?? null,
		duplicateProducts,
		reset: mutation.reset,
	};
}
