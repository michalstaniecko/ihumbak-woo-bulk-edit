import { useState, useCallback, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { useQueryClient } from '@tanstack/react-query';
import { batchSave } from '@/api/products';
import { useChangesStore } from '@/store';
import { useEditingStore } from '@/store';
import type { BatchSaveItem, BatchSaveResult } from '@/types/api';
import type { Product } from '@/types/api';

const MAX_RETRIES = 3;
const BATCH_SIZE = 50;

function delay( ms: number ): Promise< void > {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

export interface BatchSaveProgress {
	status: 'idle' | 'saving' | 'done' | 'cancelled';
	total: number;
	saved: number;
	errors: number;
	results: BatchSaveResult[];
}

export interface UseBatchSaveReturn {
	progress: BatchSaveProgress;
	isSaving: boolean;
	save: ( products: Product[] ) => Promise< void >;
	cancel: () => void;
	reset: () => void;
}

const INITIAL_PROGRESS: BatchSaveProgress = {
	status: 'idle',
	total: 0,
	saved: 0,
	errors: 0,
	results: [],
};

export function useBatchSave(): UseBatchSaveReturn {
	const [ progress, setProgress ] =
		useState< BatchSaveProgress >( INITIAL_PROGRESS );
	const abortRef = useRef< AbortController | null >( null );
	const queryClient = useQueryClient();

	const changes = useChangesStore( ( state ) => state.changes );
	const discardAll = useChangesStore( ( state ) => state.discardAll );
	const stopEditing = useEditingStore( ( state ) => state.stopEditing );

	const save = useCallback(
		async ( products: Product[] ) => {
			stopEditing();

			// Build a product lookup map for post_modified.
			const productMap = new Map< number, Product >();
			for ( const product of products ) {
				productMap.set( product.id, product );
			}

			// Convert ChangeMap to flat BatchSaveItem array.
			const items: BatchSaveItem[] = [];
			for ( const [ productIdStr, fieldChanges ] of Object.entries(
				changes
			) ) {
				const productId = Number( productIdStr );
				const product = productMap.get( productId );
				if ( ! product ) {
					continue;
				}

				for ( const [ field, change ] of Object.entries(
					fieldChanges
				) ) {
					items.push( {
						id: productId,
						field,
						value: change.newValue,
						post_modified: product.post_modified,
					} );
				}
			}

			if ( items.length === 0 ) {
				return;
			}

			// Split into batches by product count.
			const productIds = [
				...new Set( items.map( ( item ) => item.id ) ),
			];
			const batches: BatchSaveItem[][] = [];

			for ( let i = 0; i < productIds.length; i += BATCH_SIZE ) {
				const batchProductIds = new Set(
					productIds.slice( i, i + BATCH_SIZE )
				);
				batches.push(
					items.filter( ( item ) => batchProductIds.has( item.id ) )
				);
			}

			const abortController = new AbortController();
			abortRef.current = abortController;

			setProgress( {
				status: 'saving',
				total: productIds.length,
				saved: 0,
				errors: 0,
				results: [],
			} );

			const allResults: BatchSaveResult[] = [];
			let totalSaved = 0;
			let totalErrors = 0;

			for ( const batch of batches ) {
				if ( abortController.signal.aborted ) {
					setProgress( ( prev ) => ( {
						...prev,
						status: 'cancelled',
					} ) );
					break;
				}

				let lastError: unknown = null;
				let batchResult: BatchSaveResult[] | null = null;

				for (
					let attempt = 0;
					attempt <= MAX_RETRIES;
					attempt++
				) {
					if ( abortController.signal.aborted ) {
						break;
					}

					try {
						const response = await batchSave(
							batch,
							abortController.signal
						);
						batchResult = response.results;
						lastError = null;
						break;
					} catch ( error: unknown ) {
						if ( abortController.signal.aborted ) {
							break;
						}
						lastError = error;
						if ( attempt < MAX_RETRIES ) {
							// Exponential backoff: 1s, 2s, 4s.
							await delay( 1000 * Math.pow( 2, attempt ) );
						}
					}
				}

				if ( abortController.signal.aborted ) {
					setProgress( ( prev ) => ( {
						...prev,
						status: 'cancelled',
					} ) );
					break;
				}

				if ( batchResult ) {
					allResults.push( ...batchResult );
					const batchSuccess = batchResult.filter(
						( r ) => r.status === 'success'
					).length;
					const batchErrors = batchResult.filter(
						( r ) => r.status === 'error'
					).length;
					totalSaved += batchSuccess;
					totalErrors += batchErrors;
				} else {
					// All retries failed — mark all products in this batch as errors.
					const batchProductIds = [
						...new Set( batch.map( ( item ) => item.id ) ),
					];
					const errorMessage =
						lastError instanceof Error
							? lastError.message
							: __(
									'Network error after retries.',
									'ihumbak-woo-bulk-edit'
								);
					for ( const id of batchProductIds ) {
						allResults.push( {
							status: 'error',
							id,
							message: errorMessage,
						} );
					}
					totalErrors += batchProductIds.length;
				}

				setProgress( {
					status: 'saving',
					total: productIds.length,
					saved: totalSaved,
					errors: totalErrors,
					results: [ ...allResults ],
				} );
			}

			abortRef.current = null;

			const finalStatus = abortController.signal.aborted
				? 'cancelled'
				: 'done';

			setProgress( {
				status: finalStatus,
				total: productIds.length,
				saved: totalSaved,
				errors: totalErrors,
				results: allResults,
			} );

			// If anything was saved, clear changes and invalidate queries.
			if ( totalSaved > 0 ) {
				discardAll();
				await queryClient.invalidateQueries( {
					queryKey: [ 'products' ],
				} );
			}
		},
		[ changes, discardAll, stopEditing, queryClient ]
	);

	const cancel = useCallback( () => {
		if ( abortRef.current ) {
			abortRef.current.abort();
		}
	}, [] );

	const reset = useCallback( () => {
		setProgress( INITIAL_PROGRESS );
	}, [] );

	return {
		progress,
		isSaving: progress.status === 'saving',
		save,
		cancel,
		reset,
	};
}
