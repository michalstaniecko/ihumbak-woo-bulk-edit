import { useState, useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useChangelog } from '@/hooks/useChangelog';
import { useFields } from '@/hooks/useFields';
import type { ChangelogEntry, Field } from '@/types/api';

interface ChangeHistoryPanelProps {
	open: boolean;
	onClose: () => void;
}

interface Filters {
	product_id?: number;
	user_id?: number;
	field?: string;
	date_from?: string;
	date_to?: string;
}

const PER_PAGE = 50;

function formatValue( value: unknown ): string {
	if ( value === null || value === undefined || value === '' ) {
		return '—';
	}
	if ( typeof value === 'string' ) {
		return value;
	}
	if ( typeof value === 'number' || typeof value === 'boolean' ) {
		return String( value );
	}
	try {
		return JSON.stringify( value );
	} catch {
		return String( value );
	}
}

function fieldLabel( field: string, fields: Field[] | undefined ): string {
	const match = fields?.find( ( f ) => f.key === field );
	return match ? match.label : field;
}

export function ChangeHistoryPanel( {
	open,
	onClose,
}: ChangeHistoryPanelProps ): JSX.Element | null {
	const [ filters, setFilters ] = useState< Filters >( {} );
	const [ page, setPage ] = useState( 1 );
	const [ draftFilters, setDraftFilters ] = useState< Filters >( {} );

	const { data: fields } = useFields();

	const params = useMemo(
		() => ( {
			...filters,
			page,
			per_page: PER_PAGE,
		} ),
		[ filters, page ]
	);

	const { data, isLoading, isFetching, error } = useChangelog( params, open );

	if ( ! open ) {
		return null;
	}

	const handleApplyFilters = (): void => {
		setFilters( draftFilters );
		setPage( 1 );
	};

	const handleClearFilters = (): void => {
		setDraftFilters( {} );
		setFilters( {} );
		setPage( 1 );
	};

	const items: ChangelogEntry[] = data?.items ?? [];
	const total = data?.total ?? 0;
	const totalPages = data?.pages ?? 0;

	return (
		<div
			className="iwbe-changelog-overlay"
			role="dialog"
			aria-modal="true"
			aria-label={ __( 'Change History', 'ihumbak-woo-bulk-edit' ) }
		>
			<div className="iwbe-changelog-backdrop" onClick={ onClose } />
			<div className="iwbe-changelog-drawer">
				<div className="iwbe-changelog-header">
					<h2>
						{ __( 'Change History', 'ihumbak-woo-bulk-edit' ) }
					</h2>
					<button
						type="button"
						className="iwbe-changelog-close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'ihumbak-woo-bulk-edit' ) }
					>
						×
					</button>
				</div>

				<div className="iwbe-changelog-filters">
					<label>
						<span>
							{ __(
								'Product ID',
								'ihumbak-woo-bulk-edit'
							) }
						</span>
						<input
							type="number"
							min="1"
							value={ draftFilters.product_id ?? '' }
							onChange={ ( e ) =>
								setDraftFilters( {
									...draftFilters,
									product_id: e.target.value
										? Number( e.target.value )
										: undefined,
								} )
							}
						/>
					</label>
					<label>
						<span>
							{ __(
								'User ID',
								'ihumbak-woo-bulk-edit'
							) }
						</span>
						<input
							type="number"
							min="1"
							value={ draftFilters.user_id ?? '' }
							onChange={ ( e ) =>
								setDraftFilters( {
									...draftFilters,
									user_id: e.target.value
										? Number( e.target.value )
										: undefined,
								} )
							}
						/>
					</label>
					<label>
						<span>
							{ __( 'Field', 'ihumbak-woo-bulk-edit' ) }
						</span>
						<select
							value={ draftFilters.field ?? '' }
							onChange={ ( e ) =>
								setDraftFilters( {
									...draftFilters,
									field: e.target.value || undefined,
								} )
							}
						>
							<option value="">
								{ __(
									'All fields',
									'ihumbak-woo-bulk-edit'
								) }
							</option>
							{ fields?.map( ( f ) => (
								<option key={ f.key } value={ f.key }>
									{ f.label }
								</option>
							) ) }
						</select>
					</label>
					<label>
						<span>
							{ __(
								'From',
								'ihumbak-woo-bulk-edit'
							) }
						</span>
						<input
							type="date"
							value={ draftFilters.date_from ?? '' }
							onChange={ ( e ) =>
								setDraftFilters( {
									...draftFilters,
									date_from: e.target.value || undefined,
								} )
							}
						/>
					</label>
					<label>
						<span>
							{ __( 'To', 'ihumbak-woo-bulk-edit' ) }
						</span>
						<input
							type="date"
							value={ draftFilters.date_to ?? '' }
							onChange={ ( e ) =>
								setDraftFilters( {
									...draftFilters,
									date_to: e.target.value
										? `${ e.target.value } 23:59:59`
										: undefined,
								} )
							}
						/>
					</label>
					<div className="iwbe-changelog-filter-actions">
						<button
							type="button"
							className="button button-primary"
							onClick={ handleApplyFilters }
						>
							{ __( 'Apply', 'ihumbak-woo-bulk-edit' ) }
						</button>
						<button
							type="button"
							className="button"
							onClick={ handleClearFilters }
						>
							{ __(
								'Clear',
								'ihumbak-woo-bulk-edit'
							) }
						</button>
					</div>
				</div>

				<div className="iwbe-changelog-body">
					{ error && (
						<div className="iwbe-changelog-error">
							{ ( error as Error ).message }
						</div>
					) }

					{ isLoading ? (
						<div className="iwbe-changelog-loading">
							{ __(
								'Loading…',
								'ihumbak-woo-bulk-edit'
							) }
						</div>
					) : items.length === 0 ? (
						<div className="iwbe-changelog-empty">
							{ __(
								'No changes recorded yet.',
								'ihumbak-woo-bulk-edit'
							) }
						</div>
					) : (
						<table className="iwbe-changelog-table widefat striped">
							<thead>
								<tr>
									<th>
										{ __(
											'Date',
											'ihumbak-woo-bulk-edit'
										) }
									</th>
									<th>
										{ __(
											'User',
											'ihumbak-woo-bulk-edit'
										) }
									</th>
									<th>
										{ __(
											'Product',
											'ihumbak-woo-bulk-edit'
										) }
									</th>
									<th>
										{ __(
											'Field',
											'ihumbak-woo-bulk-edit'
										) }
									</th>
									<th>
										{ __(
											'Old value',
											'ihumbak-woo-bulk-edit'
										) }
									</th>
									<th>
										{ __(
											'New value',
											'ihumbak-woo-bulk-edit'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( entry ) => (
									<tr key={ entry.id }>
										<td>{ entry.changed_at }</td>
										<td>
											{ entry.user_name ||
												`#${ entry.user_id }` }
										</td>
										<td>
											<a
												href={ `${ iwbeData.adminUrl }post.php?post=${ entry.product_id }&action=edit` }
												target="_blank"
												rel="noreferrer"
											>
												{ entry.product_name ||
													`#${ entry.product_id }` }
											</a>
										</td>
										<td>
											{ fieldLabel(
												entry.field,
												fields
											) }
										</td>
										<td className="iwbe-changelog-old">
											{ formatValue(
												entry.old_value
											) }
										</td>
										<td className="iwbe-changelog-new">
											{ formatValue(
												entry.new_value
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</div>

				<div className="iwbe-changelog-footer">
					<span className="iwbe-changelog-count">
						{ sprintf(
							/* translators: %d: total changelog entries */
							__(
								'%d entries',
								'ihumbak-woo-bulk-edit'
							),
							total
						) }
						{ isFetching && ! isLoading && (
							<span className="iwbe-changelog-fetching">
								{ ' ' }
								{ __(
									'(updating…)',
									'ihumbak-woo-bulk-edit'
								) }
							</span>
						) }
					</span>
					{ totalPages > 1 && (
						<div className="iwbe-changelog-pagination">
							<button
								type="button"
								className="button"
								disabled={ page <= 1 }
								onClick={ () => setPage( page - 1 ) }
							>
								{ __(
									'Previous',
									'ihumbak-woo-bulk-edit'
								) }
							</button>
							<span>
								{ sprintf(
									/* translators: 1: current page, 2: total pages */
									__(
										'Page %1$d of %2$d',
										'ihumbak-woo-bulk-edit'
									),
									page,
									totalPages
								) }
							</span>
							<button
								type="button"
								className="button"
								disabled={ page >= totalPages }
								onClick={ () => setPage( page + 1 ) }
							>
								{ __(
									'Next',
									'ihumbak-woo-bulk-edit'
								) }
							</button>
						</div>
					) }
				</div>
			</div>
		</div>
	);
}
