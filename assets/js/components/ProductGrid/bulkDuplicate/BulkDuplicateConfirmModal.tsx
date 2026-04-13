import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ApiError } from '@/api';
import { useBulkDuplicate } from '@/hooks/useBulkDuplicate';
import type { BulkDuplicateResponse } from '@/types/api';

export interface BulkDuplicateConfirmModalProps {
	selectedIds: number[];
	selectedCount: number;
	onClose: () => void;
	onDuplicated: ( result: BulkDuplicateResponse ) => void;
}

export function BulkDuplicateConfirmModal( {
	selectedIds,
	selectedCount,
	onClose,
	onDuplicated,
}: BulkDuplicateConfirmModalProps ): JSX.Element {
	const [ copyMeta, setCopyMeta ] = useState( true );
	const [ copyImages, setCopyImages ] = useState( true );

	const { duplicateProducts, isDuplicating, lastResult, error } =
		useBulkDuplicate();

	useEffect( () => {
		const handler = ( e: KeyboardEvent ): void => {
			if ( e.key === 'Escape' && ! isDuplicating ) {
				onClose();
			}
		};
		window.addEventListener( 'keydown', handler );
		return () => window.removeEventListener( 'keydown', handler );
	}, [ onClose, isDuplicating ] );

	const canConfirm = selectedIds.length > 0 && ! isDuplicating;

	const handleConfirm = async (): Promise< void > => {
		if ( ! canConfirm ) {
			return;
		}
		try {
			const result = await duplicateProducts(
				selectedIds,
				copyMeta,
				copyImages
			);
			// Only auto-close on full success; leave the modal open with a
			// partial-failure banner so the user can see what went wrong.
			if ( result.errors === 0 ) {
				onDuplicated( result );
				onClose();
			}
		} catch {
			// Error is exposed via the hook's `error` state and rendered below.
		}
	};

	const title = sprintf(
		/* translators: %d: number of selected products */
		_n(
			'Duplicate %d product',
			'Duplicate %d products',
			selectedCount,
			'ihumbak-woo-bulk-edit'
		),
		selectedCount
	);

	const errorMessage =
		error instanceof ApiError
			? error.message
			: error instanceof Error
				? error.message
				: null;

	const partialFailure = lastResult !== undefined && lastResult.errors > 0;

	return (
		<div
			className="iwbe-bulk-modal-overlay"
			role="dialog"
			aria-modal="true"
			aria-labelledby="iwbe-bulk-duplicate-title"
		>
			<div
				className="iwbe-bulk-modal-backdrop"
				onClick={ () => {
					if ( ! isDuplicating ) {
						onClose();
					}
				} }
				aria-hidden="true"
			/>
			<div className="iwbe-bulk-modal">
				<div className="iwbe-bulk-modal-header">
					<h2 id="iwbe-bulk-duplicate-title">{ title }</h2>
					<button
						type="button"
						className="iwbe-bulk-modal-close"
						onClick={ onClose }
						disabled={ isDuplicating }
						aria-label={ __( 'Close', 'ihumbak-woo-bulk-edit' ) }
					>
						×
					</button>
				</div>

				<div className="iwbe-bulk-modal-body">
					<p className="iwbe-bulk-hint">
						{ __(
							'Duplicates will be saved as drafts.',
							'ihumbak-woo-bulk-edit'
						) }
					</p>

					<div className="iwbe-bulk-form">
						<label className="iwbe-bulk-checkbox">
							<input
								type="checkbox"
								checked={ copyMeta }
								onChange={ ( e ) =>
									setCopyMeta( e.target.checked )
								}
								disabled={ isDuplicating }
							/>
							<span>
								{ __(
									'Copy custom meta fields',
									'ihumbak-woo-bulk-edit'
								) }
							</span>
						</label>

						<label className="iwbe-bulk-checkbox">
							<input
								type="checkbox"
								checked={ copyImages }
								onChange={ ( e ) =>
									setCopyImages( e.target.checked )
								}
								disabled={ isDuplicating }
							/>
							<span>
								{ __(
									'Copy product images (featured + gallery)',
									'ihumbak-woo-bulk-edit'
								) }
							</span>
						</label>
					</div>

					{ errorMessage && (
						<div className="iwbe-bulk-error" role="alert">
							{ errorMessage }
						</div>
					) }

					{ partialFailure && lastResult && (
						<div className="iwbe-bulk-error" role="alert">
							{ sprintf(
								/* translators: 1: success count, 2: total count, 3: error count */
								__(
									'Created %1$d of %2$d duplicates. Errors: %3$d.',
									'ihumbak-woo-bulk-edit'
								),
								lastResult.success,
								lastResult.total,
								lastResult.errors
							) }
						</div>
					) }
				</div>

				<div className="iwbe-bulk-modal-footer">
					<button
						type="button"
						className="button"
						onClick={ onClose }
						disabled={ isDuplicating }
					>
						{ __( 'Cancel', 'ihumbak-woo-bulk-edit' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ handleConfirm }
						disabled={ ! canConfirm }
					>
						{ isDuplicating
							? __(
									'Duplicating…',
									'ihumbak-woo-bulk-edit'
								)
							: __( 'Duplicate', 'ihumbak-woo-bulk-edit' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
