import { useState, useCallback, useRef, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field, ProductFilter, FilterOperator, SavedFilterDefinition, TaxonomyTermDetail } from '@/types/api';
import { SavedFiltersMenu } from './SavedFiltersMenu';
import { TaxonomyTermPicker } from './TaxonomyTermPicker';
import { useTaxonomyTermLabels } from '@/hooks/useTaxonomyTerms';
import { ColumnVisibilityMenu } from './ColumnVisibilityMenu';

interface FilterToolbarProps {
	fields: Field[];
	filters: ProductFilter[];
	searchQuery: string;
	onSearchChange: ( query: string ) => void;
	onAddFilter: ( filter: ProductFilter ) => void;
	onRemoveFilter: ( index: number ) => void;
	onClearAll: () => void;
	onApplyPreset: ( definition: SavedFilterDefinition ) => void;
}

const OPERATOR_LABELS: Record< FilterOperator, string > = {
	'=': __( 'equals', 'ihumbak-woo-bulk-edit' ),
	'!=': __( 'not equals', 'ihumbak-woo-bulk-edit' ),
	'LIKE': __( 'contains', 'ihumbak-woo-bulk-edit' ),
	'NOT LIKE': __( 'not contains', 'ihumbak-woo-bulk-edit' ),
	'IS EMPTY': __( 'is empty', 'ihumbak-woo-bulk-edit' ),
	'IS NOT EMPTY': __( 'is not empty', 'ihumbak-woo-bulk-edit' ),
};

const ALL_OPERATORS: FilterOperator[] = [
	'=',
	'!=',
	'LIKE',
	'NOT LIKE',
	'IS EMPTY',
	'IS NOT EMPTY',
];

const UNARY_OPERATORS: FilterOperator[] = [ 'IS EMPTY', 'IS NOT EMPTY' ];

/** Operators for which taxonomy fields should use the term picker (ID-based). */
const TAXONOMY_ID_OPERATORS: FilterOperator[] = [ '=', '!=' ];

type DropdownStep = 'closed' | 'field' | 'operator' | 'value';

export function FilterToolbar( {
	fields,
	filters,
	searchQuery,
	onSearchChange,
	onAddFilter,
	onRemoveFilter,
	onClearAll,
	onApplyPreset,
}: FilterToolbarProps ): JSX.Element {
	const [ dropdownStep, setDropdownStep ] = useState< DropdownStep >( 'closed' );
	const [ selectedField, setSelectedField ] = useState< Field | null >( null );
	const [ selectedOperator, setSelectedOperator ] = useState< FilterOperator | null >( null );
	const [ filterValue, setFilterValue ] = useState( '' );
	const dropdownRef = useRef< HTMLDivElement >( null );
	const valueInputRef = useRef< HTMLInputElement >( null );

	const filterableFields = fields.filter( ( f ) => f.filterable );

	// Resolve term names for ID-based taxonomy chip labels.
	const termLabels = useTaxonomyTermLabels( filters, fields );

	// Close dropdown on outside click
	useEffect( () => {
		function handleClickOutside( event: MouseEvent ) {
			if (
				dropdownRef.current &&
				! dropdownRef.current.contains( event.target as Node )
			) {
				resetDropdown();
			}
		}
		if ( dropdownStep !== 'closed' ) {
			document.addEventListener( 'mousedown', handleClickOutside );
		}
		return () => {
			document.removeEventListener( 'mousedown', handleClickOutside );
		};
	}, [ dropdownStep ] );

	// Focus value input when step changes to value (for text input path).
	useEffect( () => {
		if ( dropdownStep === 'value' && valueInputRef.current ) {
			valueInputRef.current.focus();
		}
	}, [ dropdownStep ] );

	const resetDropdown = useCallback( () => {
		setDropdownStep( 'closed' );
		setSelectedField( null );
		setSelectedOperator( null );
		setFilterValue( '' );
	}, [] );

	const handleFieldSelect = useCallback( ( field: Field ) => {
		setSelectedField( field );
		setDropdownStep( 'operator' );
	}, [] );

	const handleOperatorSelect = useCallback(
		( operator: FilterOperator ) => {
			setSelectedOperator( operator );

			if ( UNARY_OPERATORS.includes( operator ) ) {
				// Unary operators don't need a value
				if ( selectedField ) {
					onAddFilter( {
						field: selectedField.key,
						operator,
					} );
					resetDropdown();
				}
			} else {
				setDropdownStep( 'value' );
			}
		},
		[ selectedField, onAddFilter, resetDropdown ]
	);

	const handleValueSubmit = useCallback( () => {
		if ( selectedField && selectedOperator && filterValue.trim() ) {
			onAddFilter( {
				field: selectedField.key,
				operator: selectedOperator,
				value: filterValue.trim(),
			} );
			resetDropdown();
		}
	}, [ selectedField, selectedOperator, filterValue, onAddFilter, resetDropdown ] );

	const handleValueKeyDown = useCallback(
		( event: React.KeyboardEvent ) => {
			if ( event.key === 'Enter' ) {
				handleValueSubmit();
			} else if ( event.key === 'Escape' ) {
				resetDropdown();
			}
		},
		[ handleValueSubmit, resetDropdown ]
	);

	// Called when TaxonomyTermPicker selects a term.
	const handleTermSelect = useCallback(
		( term: TaxonomyTermDetail ) => {
			if ( selectedField && selectedOperator ) {
				onAddFilter( {
					field: selectedField.key,
					operator: selectedOperator,
					// Store the term_id as a string — backend will detect numeric = ID path.
					value: String( term.id ),
				} );
				resetDropdown();
			}
		},
		[ selectedField, selectedOperator, onAddFilter, resetDropdown ]
	);

	const getFieldLabel = ( fieldKey: string ): string => {
		const field = fields.find( ( f ) => f.key === fieldKey );
		return field?.label ?? fieldKey;
	};

	const getOperatorLabel = ( operator: FilterOperator ): string => {
		return OPERATOR_LABELS[ operator ] ?? operator;
	};

	/**
	 * Resolve the display value for a filter chip.
	 *
	 * For taxonomy fields with = or != and a numeric ID, look up the term name
	 * from the label map. Fall back to the raw value if not yet loaded.
	 */
	const getChipValue = ( filter: ProductFilter ): string => {
		if ( filter.value === undefined ) return '';

		const field = fields.find( ( f ) => f.key === filter.field );
		if (
			field?.type === 'taxonomy' &&
			TAXONOMY_ID_OPERATORS.includes( filter.operator ) &&
			/^\d+$/.test( filter.value )
		) {
			const label = termLabels[ `${ filter.field }:${ filter.value }` ];
			return label ?? filter.value;
		}

		return filter.value;
	};

	const renderSelectOptions = ( field: Field ): JSX.Element => {
		const options = field.options ?? {};
		return (
			<div className="iwbe-filter-dropdown-list">
				{ Object.entries( options ).map( ( [ value, label ] ) => (
					<button
						key={ value }
						type="button"
						className="iwbe-filter-dropdown-item"
						onClick={ () => {
							if ( selectedField && selectedOperator ) {
								onAddFilter( {
									field: selectedField.key,
									operator: selectedOperator,
									value,
								} );
								resetDropdown();
							}
						} }
					>
						{ label }
					</button>
				) ) }
			</div>
		);
	};

	/**
	 * Render the value step of the filter dropdown.
	 *
	 * - For taxonomy fields with = / !=: render TaxonomyTermPicker
	 * - For taxonomy fields with LIKE / NOT LIKE: classic text input
	 * - For select fields: option list
	 * - Default: numeric or text input
	 */
	const renderValueStep = (): JSX.Element | null => {
		if ( ! selectedField || ! selectedOperator ) return null;

		const isTaxonomy = selectedField.type === 'taxonomy';
		const isTaxonomyIdOp = TAXONOMY_ID_OPERATORS.includes( selectedOperator );

		if ( isTaxonomy && isTaxonomyIdOp ) {
			return (
				<div className="iwbe-filter-dropdown-value">
					<TaxonomyTermPicker
						fieldKey={ selectedField.key }
						onSelect={ handleTermSelect }
						onCancel={ resetDropdown }
					/>
				</div>
			);
		}

		if ( selectedField.type === 'select' ) {
			return renderSelectOptions( selectedField );
		}

		return (
			<div className="iwbe-filter-dropdown-value">
				<input
					ref={ valueInputRef }
					type={
						selectedField.type === 'number' ||
						selectedField.type === 'price' ||
						selectedField.type === 'integer'
							? 'number'
							: 'text'
					}
					className="iwbe-filter-value-input"
					placeholder={ __( 'Enter value…', 'ihumbak-woo-bulk-edit' ) }
					value={ filterValue }
					onChange={ ( e ) => setFilterValue( e.target.value ) }
					onKeyDown={ handleValueKeyDown }
				/>
				<button
					type="button"
					className="iwbe-filter-value-submit"
					onClick={ handleValueSubmit }
					disabled={ ! filterValue.trim() }
				>
					{ __( 'Apply', 'ihumbak-woo-bulk-edit' ) }
				</button>
			</div>
		);
	};

	return (
		<div className="iwbe-filter-toolbar">
			<div className="iwbe-filter-toolbar-row">
				<div className="iwbe-filter-search">
					<input
						type="text"
						className="iwbe-filter-search-input"
						placeholder={ __(
							'Search by name or SKU…',
							'ihumbak-woo-bulk-edit'
						) }
						value={ searchQuery }
						onChange={ ( e ) => onSearchChange( e.target.value ) }
					/>
				</div>

				<div className="iwbe-filter-add" ref={ dropdownRef }>
					<button
						type="button"
						className="iwbe-filter-add-btn"
						onClick={ () =>
							setDropdownStep(
								dropdownStep === 'closed' ? 'field' : 'closed'
							)
						}
					>
						<span className="iwbe-filter-add-icon">+</span>
						{ __( 'Add Filter', 'ihumbak-woo-bulk-edit' ) }
					</button>

					{ dropdownStep !== 'closed' && (
						<div className="iwbe-filter-dropdown">
							{ dropdownStep === 'field' && (
								<>
									<div className="iwbe-filter-dropdown-title">
										{ __( 'Select field', 'ihumbak-woo-bulk-edit' ) }
									</div>
									<div className="iwbe-filter-dropdown-list">
										{ filterableFields.map( ( field ) => (
											<button
												key={ field.key }
												type="button"
												className="iwbe-filter-dropdown-item"
												onClick={ () =>
													handleFieldSelect( field )
												}
											>
												{ field.label }
											</button>
										) ) }
									</div>
								</>
							) }

							{ dropdownStep === 'operator' && selectedField && (
								<>
									<div className="iwbe-filter-dropdown-title">
										{ selectedField.label }
										{ ' — ' }
										{ __( 'select operator', 'ihumbak-woo-bulk-edit' ) }
									</div>
									<div className="iwbe-filter-dropdown-list">
										{ ALL_OPERATORS.map( ( op ) => (
											<button
												key={ op }
												type="button"
												className="iwbe-filter-dropdown-item"
												onClick={ () =>
													handleOperatorSelect( op )
												}
											>
												{ OPERATOR_LABELS[ op ] }
											</button>
										) ) }
									</div>
								</>
							) }

							{ dropdownStep === 'value' &&
								selectedField &&
								selectedOperator && (
									<>
										<div className="iwbe-filter-dropdown-title">
											{ selectedField.label }{ ' ' }
											{ getOperatorLabel( selectedOperator ) }
										</div>
										{ renderValueStep() }
									</>
								) }
						</div>
					) }
				</div>

				<SavedFiltersMenu
					currentFilters={ filters }
					currentSearch={ searchQuery }
					onApplyPreset={ onApplyPreset }
				/>

				<ColumnVisibilityMenu fields={ fields } />

				{ filters.length > 0 && (
					<button
						type="button"
						className="iwbe-filter-clear-all"
						onClick={ onClearAll }
					>
						{ __( 'Clear all', 'ihumbak-woo-bulk-edit' ) }
					</button>
				) }
			</div>

			{ filters.length > 0 && (
				<div className="iwbe-filter-chips">
					{ filters.map( ( filter, index ) => (
						<div key={ index } className="iwbe-filter-chip">
							<span className="iwbe-chip-field">
								{ getFieldLabel( filter.field ) }
							</span>
							<span className="iwbe-chip-operator">
								{ getOperatorLabel( filter.operator ) }
							</span>
							{ filter.value !== undefined && (
								<span className="iwbe-chip-value">
									{ getChipValue( filter ) }
								</span>
							) }
							<button
								type="button"
								className="iwbe-chip-remove"
								onClick={ () => onRemoveFilter( index ) }
								aria-label={ __(
									'Remove filter',
									'ihumbak-woo-bulk-edit'
								) }
							>
								×
							</button>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
