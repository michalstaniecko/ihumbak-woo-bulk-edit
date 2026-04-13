import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useChangesStore, useEditingStore } from '@/store';
import type { BulkDeleteResponse, Product } from '@/types/api';
import type { UseBatchSaveReturn } from '@/hooks/useBatchSave';
import { BulkDeleteConfirmModal } from './bulkDelete';

interface StatusBarProps {
	total: number;
	selectedCount: number;
	isFetching: boolean;
	products: Product[];
	selectedProducts: Product[];
	batchSave: UseBatchSaveReturn;
	onDeleted: ( result: BulkDeleteResponse ) => void;
}

export function StatusBar( {
	total,
	selectedCount,
	isFetching,
	products,
	selectedProducts,
	batchSave,
	onDeleted,
}: StatusBarProps ): JSX.Element {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const changes = useChangesStore( ( state ) => state.changes );
	const discardAll = useChangesStore( ( state ) => state.discardAll );
	const stopEditing = useEditingStore( ( state ) => state.stopEditing );

	const { progress, isSaving, save, cancel, reset } = batchSave;

	const changedProductsCount = Object.keys( changes ).length;
	let changedCellsCount = 0;
	for ( const productId of Object.keys( changes ) ) {
		changedCellsCount += Object.keys( changes[ productId ] ).length;
	}
	const hasChanges = changedProductsCount > 0;

	const handleSave = (): void => {
		void save( products );
	};

	// Progress bar rendering.
	if ( isSaving ) {
		const percent =
			progress.total > 0
				? Math.round(
						( ( progress.saved + progress.errors ) /
							progress.total ) *
							100
					)
				: 0;

		return (
			<div className="iwbe-status-bar">
				<div className="iwbe-save-progress">
					<div className="iwbe-progress-bar">
						<div
							className="iwbe-progress-fill"
							style={ { width: `${ percent }%` } }
						/>
					</div>
					<span className="iwbe-progress-text">
						{ sprintf(
							/* translators: %1$d: saved count, %2$d: total count */
							__(
								'%1$d / %2$d products',
								'ihumbak-woo-bulk-edit'
							),
							progress.saved + progress.errors,
							progress.total
						) }
					</span>
					<button
						type="button"
						className="iwbe-btn-cancel"
						onClick={ cancel }
					>
						{ __( 'Cancel', 'ihumbak-woo-bulk-edit' ) }
					</button>
				</div>
			</div>
		);
	}

	// Summary after save completes.
	if ( progress.status === 'done' || progress.status === 'cancelled' ) {
		return (
			<div className="iwbe-status-bar">
				<div className="iwbe-save-summary">
					{ progress.status === 'cancelled' && (
						<span className="iwbe-summary-cancelled">
							{ __( 'Cancelled.', 'ihumbak-woo-bulk-edit' ) }
						</span>
					) }
					<span className="iwbe-summary-success">
						{ sprintf(
							/* translators: %d: number of saved products */
							__( '%d saved', 'ihumbak-woo-bulk-edit' ),
							progress.saved
						) }
					</span>
					{ progress.errors > 0 && (
						<span className="iwbe-summary-errors">
							{ sprintf(
								/* translators: %d: number of errors */
								__( '%d errors', 'ihumbak-woo-bulk-edit' ),
								progress.errors
							) }
						</span>
					) }
					<button
						type="button"
						className="iwbe-btn-dismiss"
						onClick={ reset }
					>
						{ __( 'OK', 'ihumbak-woo-bulk-edit' ) }
					</button>
				</div>
			</div>
		);
	}

	const selectedIds = selectedProducts.map( ( p ) => p.id );

	return (
		<div className="iwbe-status-bar">
			<span className="iwbe-status-total">
				{ sprintf(
					/* translators: %d: number of products */
					__( '%d products', 'ihumbak-woo-bulk-edit' ),
					total
				) }
			</span>

			{ selectedCount > 0 && (
				<span className="iwbe-status-selected">
					{ sprintf(
						/* translators: %d: number of selected products */
						__( '%d selected', 'ihumbak-woo-bulk-edit' ),
						selectedCount
					) }
				</span>
			) }

			<button
				type="button"
				className="iwbe-btn-delete-selected"
				onClick={ () => setShowDeleteModal( true ) }
				disabled={ selectedCount === 0 }
			>
				{ __( 'Delete selected', 'ihumbak-woo-bulk-edit' ) }
			</button>

			{ hasChanges && (
				<>
					<span className="iwbe-status-changes">
						{ sprintf(
							/* translators: %1$d: changed cells, %2$d: affected products */
							__(
								'%1$d cells changed in %2$d products',
								'ihumbak-woo-bulk-edit'
							),
							changedCellsCount,
							changedProductsCount
						) }
					</span>
					<button
						type="button"
						className="iwbe-btn-save"
						onClick={ handleSave }
					>
						{ __( 'Save Changes', 'ihumbak-woo-bulk-edit' ) }
					</button>
					<button
						type="button"
						className="iwbe-btn-discard"
						onClick={ () => {
							stopEditing();
							discardAll();
						} }
					>
						{ __( 'Discard Changes', 'ihumbak-woo-bulk-edit' ) }
					</button>
				</>
			) }

			{ isFetching && (
				<span className="iwbe-status-loading">
					{ __( 'Loading\u2026', 'ihumbak-woo-bulk-edit' ) }
				</span>
			) }

			{ showDeleteModal && (
				<BulkDeleteConfirmModal
					selectedIds={ selectedIds }
					selectedCount={ selectedCount }
					onClose={ () => setShowDeleteModal( false ) }
					onConfirmed={ ( result ) => onDeleted( result ) }
				/>
			) }
		</div>
	);
}
