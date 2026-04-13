import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ApiError } from '@/api';
import { useBulkDelete } from '@/hooks/useBulkDelete';
import type { BulkDeleteMode, BulkDeleteResponse } from '@/types/api';

export interface BulkDeleteConfirmModalProps {
	selectedIds: number[];
	selectedCount: number;
	onClose: () => void;
	onConfirmed: ( result: BulkDeleteResponse ) => void;
}

const CONFIRM_PHRASE = 'DELETE';

export function BulkDeleteConfirmModal( {
	selectedIds,
	selectedCount,
	onClose,
	onConfirmed,
}: BulkDeleteConfirmModalProps ): JSX.Element {
	const [ mode, setMode ] = useState< BulkDeleteMode >( 'trash' );
	const [ typedConfirm, setTypedConfirm ] = useState( '' );

	const { deleteProducts, isDeleting, lastResult, error } = useBulkDelete();

	useEffect( () => {
		const handler = ( e: KeyboardEvent ): void => {
			if ( e.key === 'Escape' && ! isDeleting ) {
				onClose();
			}
		};
		window.addEventListener( 'keydown', handler );
		return () => window.removeEventListener( 'keydown', handler );
	}, [ onClose, isDeleting ] );

	const isPermanent = mode === 'permanent';
	const permanentConfirmed =
		! isPermanent || typedConfirm.trim() === CONFIRM_PHRASE;
	const canConfirm =
		selectedIds.length > 0 && ! isDeleting && permanentConfirmed;

	const handleConfirm = async (): Promise< void > => {
		if ( ! canConfirm ) {
			return;
		}
		try {
			const result = await deleteProducts( selectedIds, mode );
			onConfirmed( result );
			onClose();
		} catch {
			// Error is exposed via the hook's `error` state and rendered below.
		}
	};

	const title = sprintf(
		/* translators: %d: number of selected products */
		_n(
			'Delete %d product',
			'Delete %d products',
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

	const partialFailure =
		lastResult !== undefined &&
		( lastResult.errors > 0 || lastResult.variation_errors > 0 );

	return (
		<div
			className="iwbe-bulk-modal-overlay"
			role="dialog"
			aria-modal="true"
			aria-labelledby="iwbe-bulk-delete-title"
		>
			<div
				className="iwbe-bulk-modal-backdrop"
				onClick={ () => {
					if ( ! isDeleting ) {
						onClose();
					}
				} }
				aria-hidden="true"
			/>
			<div className="iwbe-bulk-modal">
				<div className="iwbe-bulk-modal-header">
					<h2 id="iwbe-bulk-delete-title">{ title }</h2>
					<button
						type="button"
						className="iwbe-bulk-modal-close"
						onClick={ onClose }
						disabled={ isDeleting }
						aria-label={ __( 'Close', 'ihumbak-woo-bulk-edit' ) }
					>
						×
					</button>
				</div>

				<div className="iwbe-bulk-modal-body">
					<fieldset className="iwbe-bulk-delete-mode">
						<legend>
							{ __(
								'Choose how to delete',
								'ihumbak-woo-bulk-edit'
							) }
						</legend>

						<label className="iwbe-bulk-delete-option">
							<input
								type="radio"
								name="iwbe-bulk-delete-mode"
								value="trash"
								checked={ mode === 'trash' }
								onChange={ () => setMode( 'trash' ) }
								disabled={ isDeleting }
							/>
							<span className="iwbe-bulk-delete-option-label">
								{ __(
									'Move to Trash',
									'ihumbak-woo-bulk-edit'
								) }
							</span>
							<span className="iwbe-bulk-delete-option-caption">
								{ __(
									'Products can be restored from the Trash within 30 days.',
									'ihumbak-woo-bulk-edit'
								) }
							</span>
						</label>

						<label className="iwbe-bulk-delete-option">
							<input
								type="radio"
								name="iwbe-bulk-delete-mode"
								value="permanent"
								checked={ mode === 'permanent' }
								onChange={ () => setMode( 'permanent' ) }
								disabled={ isDeleting }
							/>
							<span className="iwbe-bulk-delete-option-label">
								{ __(
									'Delete permanently',
									'ihumbak-woo-bulk-edit'
								) }
							</span>
							<span className="iwbe-bulk-delete-option-caption">
								{ __(
									'This cannot be undone. Variations are also removed.',
									'ihumbak-woo-bulk-edit'
								) }
							</span>
						</label>
					</fieldset>

					{ isPermanent && (
						<div
							className="iwbe-bulk-delete-warning"
							role="alert"
						>
							<p>
								<strong>
									{ __(
										'Warning:',
										'ihumbak-woo-bulk-edit'
									) }
								</strong>{ ' ' }
								{ __(
									'Permanent deletion cannot be undone. Type DELETE to confirm.',
									'ihumbak-woo-bulk-edit'
								) }
							</p>
							<input
								type="text"
								className="iwbe-bulk-delete-confirm-input"
								value={ typedConfirm }
								onChange={ ( e ) =>
									setTypedConfirm( e.target.value )
								}
								placeholder={ CONFIRM_PHRASE }
								disabled={ isDeleting }
								aria-label={ __(
									'Type DELETE to confirm permanent deletion',
									'ihumbak-woo-bulk-edit'
								) }
							/>
						</div>
					) }

					{ errorMessage && (
						<div className="iwbe-bulk-error" role="alert">
							{ errorMessage }
						</div>
					) }

					{ partialFailure && lastResult && (
						<div className="iwbe-bulk-error" role="alert">
							{ sprintf(
								/* translators: 1: success count, 2: error count */
								__(
									'%1$d deleted, %2$d failed.',
									'ihumbak-woo-bulk-edit'
								),
								lastResult.success,
								lastResult.errors
							) }
							{ lastResult.variation_errors > 0 && (
								<>
									{ ' ' }
									{ sprintf(
										/* translators: %d: number of variations that failed to delete */
										_n(
											'%d variation could not be deleted and is now orphaned.',
											'%d variations could not be deleted and are now orphaned.',
											lastResult.variation_errors,
											'ihumbak-woo-bulk-edit'
										),
										lastResult.variation_errors
									) }
								</>
							) }
						</div>
					) }
				</div>

				<div className="iwbe-bulk-modal-footer">
					<button
						type="button"
						className="button"
						onClick={ onClose }
						disabled={ isDeleting }
					>
						{ __( 'Cancel', 'ihumbak-woo-bulk-edit' ) }
					</button>
					<button
						type="button"
						className="button iwbe-button-danger"
						onClick={ handleConfirm }
						disabled={ ! canConfirm }
					>
						{ isDeleting
							? __( 'Deleting…', 'ihumbak-woo-bulk-edit' )
							: isPermanent
								? __(
										'Delete permanently',
										'ihumbak-woo-bulk-edit'
									)
								: __(
										'Move to Trash',
										'ihumbak-woo-bulk-edit'
									) }
					</button>
				</div>
			</div>
		</div>
	);
}
