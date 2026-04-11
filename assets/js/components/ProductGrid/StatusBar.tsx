import { __, sprintf } from '@wordpress/i18n';

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

			{ isFetching && (
				<span className="iwbe-status-loading">
					{ __( 'Loading\u2026', 'ihumbak-woo-bulk-edit' ) }
				</span>
			) }
		</div>
	);
}
