import { __ } from '@wordpress/i18n';

interface PaginationProps {
	page: number;
	totalPages: number;
	perPage: number;
	onPageChange: ( page: number ) => void;
	onPerPageChange: ( perPage: number ) => void;
	isLoading: boolean;
}

const PER_PAGE_OPTIONS = [ 25, 50, 100, 250, 500 ];

export function Pagination( {
	page,
	totalPages,
	perPage,
	onPageChange,
	onPerPageChange,
	isLoading,
}: PaginationProps ): JSX.Element {
	const isFirst = page <= 1;
	const isLast = page >= totalPages;

	return (
		<div className="iwbe-pagination">
			<div className="iwbe-pagination-pages">
				<button
					className="iwbe-pagination-btn"
					onClick={ () => onPageChange( 1 ) }
					disabled={ isFirst || isLoading }
					title={ __( 'First page', 'ihumbak-woo-bulk-edit' ) }
				>
					&laquo;
				</button>
				<button
					className="iwbe-pagination-btn"
					onClick={ () => onPageChange( page - 1 ) }
					disabled={ isFirst || isLoading }
					title={ __( 'Previous page', 'ihumbak-woo-bulk-edit' ) }
				>
					&lsaquo;
				</button>
				<span className="iwbe-pagination-info">
					{ `${ page } / ${ totalPages || 1 }` }
				</span>
				<button
					className="iwbe-pagination-btn"
					onClick={ () => onPageChange( page + 1 ) }
					disabled={ isLast || isLoading }
					title={ __( 'Next page', 'ihumbak-woo-bulk-edit' ) }
				>
					&rsaquo;
				</button>
				<button
					className="iwbe-pagination-btn"
					onClick={ () => onPageChange( totalPages ) }
					disabled={ isLast || isLoading }
					title={ __( 'Last page', 'ihumbak-woo-bulk-edit' ) }
				>
					&raquo;
				</button>
			</div>

			<div className="iwbe-pagination-per-page">
				<label htmlFor="iwbe-per-page">
					{ __( 'Per page:', 'ihumbak-woo-bulk-edit' ) }
				</label>
				<select
					id="iwbe-per-page"
					value={ perPage }
					onChange={ ( e ) =>
						onPerPageChange( Number( e.target.value ) )
					}
					disabled={ isLoading }
				>
					{ PER_PAGE_OPTIONS.map( ( option ) => (
						<option key={ option } value={ option }>
							{ option }
						</option>
					) ) }
				</select>
			</div>
		</div>
	);
}
