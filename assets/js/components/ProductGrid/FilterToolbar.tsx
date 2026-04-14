import { useState, useCallback, useRef, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field, ProductFilter, FilterOperator, SavedFilterDefinition } from '@/types/api';
import { SavedFiltersMenu } from './SavedFiltersMenu';

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

	// Focus value input when step changes to value
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

	const getFieldLabel = ( fieldKey: string ): string => {
		const field = fields.find( ( f ) => f.key === fieldKey );
		return field?.label ?? fieldKey;
	};

	const getOperatorLabel = ( operator: FilterOperator ): string => {
		return OPERATOR_LABELS[ operator ] ?? operator;
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
										{ selectedField.type === 'select' ? (
											renderSelectOptions( selectedField )
										) : (
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
													placeholder={ __(
														'Enter value…',
														'ihumbak-woo-bulk-edit'
													) }
													value={ filterValue }
													onChange={ ( e ) =>
														setFilterValue( e.target.value )
													}
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
										) }
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
									{ filter.value }
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
