import { useCallback, useEffect, useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import type { Field, Product, ProductFilter, Sort, FilterGroup } from '@/types/api';
import { useChangesStore } from '@/store';
import {
	applyBulkOperation,
	applyNumericOperation,
	inferBulkOperationKind,
	type BulkOperation,
	type BulkOperationKind,
	type NumericOperation,
	type TextOperation,
	type BooleanOperation,
	type TaxonomyOperation,
} from '../bulkOperations';
import { NumericBulkForm, type NumericBase } from './NumericBulkForm';
import { TextBulkForm } from './TextBulkForm';
import { BooleanBulkForm } from './BooleanBulkForm';
import { TaxonomyBulkForm } from './TaxonomyBulkForm';
import { fetchAllFilteredProducts } from './fetchAllFilteredProducts';

type ApplyScope = 'selected' | 'filtered';

export interface BulkEditModalProps {
	field: Field;
	selectedProducts: Product[];
	totalFiltered: number;
	filters: ProductFilter[] | FilterGroup;
	sort: Sort;
	onClose: () => void;
}

export function BulkEditModal( {
	field,
	selectedProducts,
	totalFiltered,
	filters,
	sort,
	onClose,
}: BulkEditModalProps ): JSX.Element {
	const setChange = useChangesStore( ( s ) => s.setChange );
	const getChangedValue = useChangesStore( ( s ) => s.getChangedValue );

	const kind = useMemo< BulkOperationKind | null >(
		() => inferBulkOperationKind( field ),
		[ field ]
	);

	const [ scope, setScope ] = useState< ApplyScope >(
		selectedProducts.length > 0 ? 'selected' : 'filtered'
	);
	const [ numericOp, setNumericOp ] = useState< NumericOperation | null >(
		null
	);
	const [ numericBase, setNumericBase ] = useState< NumericBase >(
		'current_sale_price'
	);
	const [ textOp, setTextOp ] = useState< TextOperation | null >( null );
	const [ booleanOp, setBooleanOp ] = useState< BooleanOperation | null >(
		null
	);
	const [ taxonomyOp, setTaxonomyOp ] = useState< TaxonomyOperation | null >(
		null
	);

	const [ applying, setApplying ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ notice, setNotice ] = useState< string | null >( null );
	const [ progress, setProgress ] = useState< {
		loaded: number;
		total: number;
	} | null >( null );

	useEffect( () => {
		const handler = ( e: KeyboardEvent ): void => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		window.addEventListener( 'keydown', handler );
		return () => window.removeEventListener( 'keydown', handler );
	}, [ onClose ] );

	useEffect( () => {
		setNotice( null );
	}, [ numericOp, textOp, booleanOp, taxonomyOp, numericBase, scope ] );

	const currentOperation: BulkOperation | null = useMemo( () => {
		if ( kind === 'numeric' && numericOp ) {
			return { kind: 'numeric', op: numericOp };
		}
		if ( kind === 'text' && textOp ) {
			return { kind: 'text', op: textOp };
		}
		if ( kind === 'boolean' && booleanOp ) {
			return { kind: 'boolean', op: booleanOp };
		}
		if ( kind === 'taxonomy' && taxonomyOp ) {
			return { kind: 'taxonomy', op: taxonomyOp };
		}
		return null;
	}, [ kind, numericOp, textOp, booleanOp, taxonomyOp ] );

	const applyToProducts = useCallback(
		( products: Product[], operation: BulkOperation ) => {
			let applied = 0;
			let skipped = 0;

			const useRegularBase =
				operation.kind === 'numeric' &&
				field.key === 'sale_price' &&
				numericBase === 'current_regular_price' &&
				operation.op.type !== 'set' &&
				operation.op.type !== 'clear';

			for ( const product of products ) {
				const serverValue = product[
					field.key as keyof Product
				] as unknown;
				const pending = getChangedValue( product.id, field.key );
				const currentValue =
					pending !== undefined ? pending : serverValue;

				let newValue: unknown;

				if ( useRegularBase && operation.kind === 'numeric' ) {
					const regularPending = getChangedValue(
						product.id,
						'regular_price'
					);
					const regularBaseRaw =
						regularPending !== undefined
							? regularPending
							: product.regular_price;
					const regularBaseNum = parseFloat(
						String( regularBaseRaw ?? '' )
					);
					if ( ! Number.isFinite( regularBaseNum ) ) {
						skipped += 1;
						continue;
					}
					newValue = applyNumericOperation(
						currentValue,
						operation.op,
						field,
						regularBaseNum
					);
				} else {
					newValue = applyBulkOperation(
						currentValue,
						operation,
						field
					);
				}

				const originalOldValue = serverValue;
				setChange( product.id, field.key, originalOldValue, newValue );
				applied += 1;
			}
			return { applied, skipped };
		},
		[ field, getChangedValue, setChange, numericBase ]
	);

	const handleApply = async (): Promise< void > => {
		if ( ! currentOperation ) {
			return;
		}
		setError( null );
		setNotice( null );
		setApplying( true );
		setProgress( null );

		try {
			let targets: Product[];
			if ( scope === 'selected' ) {
				targets = selectedProducts;
			} else {
				targets = await fetchAllFilteredProducts( {
					filters,
					sort,
					onProgress: ( loaded, total ) =>
						setProgress( { loaded, total } ),
				} );
			}

			const { applied, skipped } = applyToProducts(
				targets,
				currentOperation
			);
			if ( applied === 0 && skipped === 0 ) {
				setNotice(
					__(
						'No products to update.',
						'ihumbak-woo-bulk-edit'
					)
				);
			} else if ( skipped > 0 ) {
				setNotice(
					sprintf(
						/* translators: 1: number of products updated, 2: number skipped */
						__(
							'%1$d products updated, %2$d skipped (missing regular price)',
							'ihumbak-woo-bulk-edit'
						),
						applied,
						skipped
					)
				);
			} else {
				onClose();
			}
		} catch ( err ) {
			const message =
				err instanceof Error
					? err.message
					: __(
							'Failed to apply operation.',
							'ihumbak-woo-bulk-edit'
					  );
			setError( message );
		} finally {
			setApplying( false );
			setProgress( null );
		}
	};

	const renderForm = (): JSX.Element => {
		if ( kind === 'numeric' ) {
			return (
				<NumericBulkForm
					field={ field }
					onChange={ ( op, nextBase ) => {
						setNumericOp( op );
						setNumericBase( nextBase );
					} }
				/>
			);
		}
		if ( kind === 'text' ) {
			return <TextBulkForm field={ field } onChange={ setTextOp } />;
		}
		if ( kind === 'boolean' ) {
			return <BooleanBulkForm onChange={ setBooleanOp } />;
		}
		if ( kind === 'taxonomy' ) {
			return <TaxonomyBulkForm onChange={ setTaxonomyOp } />;
		}
		return (
			<p className="iwbe-bulk-unsupported">
				{ __(
					'Bulk editing is not supported for this field type yet.',
					'ihumbak-woo-bulk-edit'
				) }
			</p>
		);
	};

	const selectedCount = selectedProducts.length;
	const canApply =
		currentOperation !== null &&
		! applying &&
		( scope === 'filtered' || selectedCount > 0 );

	return (
		<div
			className="iwbe-bulk-modal-overlay"
			role="dialog"
			aria-modal="true"
		>
			<div
				className="iwbe-bulk-modal-backdrop"
				onClick={ onClose }
				aria-hidden="true"
			/>
			<div className="iwbe-bulk-modal">
				<div className="iwbe-bulk-modal-header">
					<h2>
						{ sprintf(
							/* translators: %s: field label */
							__( 'Bulk edit: %s', 'ihumbak-woo-bulk-edit' ),
							field.label
						) }
					</h2>
					<button
						type="button"
						className="iwbe-bulk-modal-close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'ihumbak-woo-bulk-edit' ) }
					>
						×
					</button>
				</div>

				<div className="iwbe-bulk-modal-body">
					{ renderForm() }

					<div className="iwbe-bulk-scope">
						<label className="iwbe-bulk-field">
							<span>
								{ __( 'Apply to', 'ihumbak-woo-bulk-edit' ) }
							</span>
							<select
								value={ scope }
								onChange={ ( e ) =>
									setScope( e.target.value as ApplyScope )
								}
							>
								<option
									value="selected"
									disabled={ selectedCount === 0 }
								>
									{ sprintf(
										/* translators: %d: number of selected rows */
										__(
											'Selected rows (%d)',
											'ihumbak-woo-bulk-edit'
										),
										selectedCount
									) }
								</option>
								<option value="filtered">
									{ sprintf(
										/* translators: %d: total matching products */
										__(
											'All filtered results (%d)',
											'ihumbak-woo-bulk-edit'
										),
										totalFiltered
									) }
								</option>
							</select>
						</label>
					</div>

					{ error && (
						<div className="iwbe-bulk-error">{ error }</div>
					) }
					{ notice && (
						<div
							className="iwbe-bulk-notice"
							role="status"
							aria-live="polite"
						>
							{ notice }
						</div>
					) }
					{ applying && progress && (
						<div className="iwbe-bulk-progress">
							{ sprintf(
								/* translators: 1: loaded count, 2: total count */
								__(
									'Loading products… %1$d / %2$d',
									'ihumbak-woo-bulk-edit'
								),
								progress.loaded,
								progress.total
							) }
						</div>
					) }
				</div>

				<div className="iwbe-bulk-modal-footer">
					<button
						type="button"
						className="button"
						onClick={ onClose }
						disabled={ applying }
					>
						{ __( 'Cancel', 'ihumbak-woo-bulk-edit' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ handleApply }
						disabled={ ! canApply }
					>
						{ applying
							? __( 'Applying…', 'ihumbak-woo-bulk-edit' )
							: __( 'Apply', 'ihumbak-woo-bulk-edit' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
