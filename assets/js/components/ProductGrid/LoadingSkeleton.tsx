import { __ } from '@wordpress/i18n';

const SKELETON_ROWS = 10;
const SKELETON_COLS = 6;

export function LoadingSkeleton(): JSX.Element {
	return (
		<div className="iwbe-skeleton" role="status" aria-label={ __( 'Loading products', 'ihumbak-woo-bulk-edit' ) }>
			<table className="iwbe-grid-table iwbe-skeleton-table">
				<thead>
					<tr>
						{ Array.from( { length: SKELETON_COLS }, ( _, i ) => (
							<th key={ i } className="iwbe-th">
								<span className="iwbe-skeleton-bar iwbe-skeleton-bar-header" />
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ Array.from( { length: SKELETON_ROWS }, ( _, rowIdx ) => (
						<tr key={ rowIdx } className="iwbe-skeleton-row">
							{ Array.from(
								{ length: SKELETON_COLS },
								( _, colIdx ) => (
									<td key={ colIdx } className="iwbe-td">
										<span className="iwbe-skeleton-bar" />
									</td>
								)
							) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
