import { __, sprintf } from '@wordpress/i18n';
import { useChangesStore } from '@/store';

interface StatusBarProps {
	total: number;
	selectedCount: number;
	isFetching: boolean;
}

export function StatusBar( {
	total,
	selectedCount,
	isFetching,
}: StatusBarProps ): JSX.Element {
	const changes = useChangesStore( ( state ) => state.changes );
	const discardAll = useChangesStore( ( state ) => state.discardAll );

	const changedProductsCount = Object.keys( changes ).length;
	let changedCellsCount = 0;
	for ( const productId of Object.keys( changes ) ) {
		changedCellsCount += Object.keys( changes[ productId ] ).length;
	}
	const hasChanges = changedProductsCount > 0;

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
						className="iwbe-btn-discard"
						onClick={ discardAll }
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
		</div>
	);
}
