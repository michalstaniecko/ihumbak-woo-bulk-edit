import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field, FilterCondition, FilterOperator, TaxonomyTermDetail } from '@/types/api';
import { TaxonomyTermPicker } from '../TaxonomyTermPicker';

// ── Operator metadata ─────────────────────────────────────────────────────────

interface OperatorMeta {
	label: string;
	/** Input variant for the value step */
	valueMode: 'none' | 'single' | 'multi' | 'range';
	/** Field types this operator applies to (empty = all) */
	types?: Field[ 'type' ][];
}

const OPERATOR_META: Record< FilterOperator, OperatorMeta > = {
	'=': { label: __( 'equals', 'ihumbak-woo-bulk-edit' ), valueMode: 'single' },
	'!=': { label: __( 'not equals', 'ihumbak-woo-bulk-edit' ), valueMode: 'single' },
	LIKE: { label: __( 'contains', 'ihumbak-woo-bulk-edit' ), valueMode: 'single' },
	'NOT LIKE': { label: __( 'not contains', 'ihumbak-woo-bulk-edit' ), valueMode: 'single' },
	'IS EMPTY': { label: __( 'is empty', 'ihumbak-woo-bulk-edit' ), valueMode: 'none' },
	'IS NOT EMPTY': { label: __( 'is not empty', 'ihumbak-woo-bulk-edit' ), valueMode: 'none' },
	'<': {
		label: __( 'less than', 'ihumbak-woo-bulk-edit' ),
		valueMode: 'single',
		types: [ 'number', 'price', 'integer' ],
	},
	'<=': {
		label: __( 'less than or equal', 'ihumbak-woo-bulk-edit' ),
		valueMode: 'single',
		types: [ 'number', 'price', 'integer' ],
	},
	'>': {
		label: __( 'greater than', 'ihumbak-woo-bulk-edit' ),
		valueMode: 'single',
		types: [ 'number', 'price', 'integer' ],
	},
	'>=': {
		label: __( 'greater than or equal', 'ihumbak-woo-bulk-edit' ),
		valueMode: 'single',
		types: [ 'number', 'price', 'integer' ],
	},
	IN: { label: __( 'in list', 'ihumbak-woo-bulk-edit' ), valueMode: 'multi' },
	'NOT IN': { label: __( 'not in list', 'ihumbak-woo-bulk-edit' ), valueMode: 'multi' },
	BETWEEN: {
		label: __( 'between', 'ihumbak-woo-bulk-edit' ),
		valueMode: 'range',
		types: [ 'number', 'price', 'integer' ],
	},
	REGEXP: { label: __( 'matches pattern', 'ihumbak-woo-bulk-edit' ), valueMode: 'single' },
};

/**
 * Operators that use the TaxonomyTermPicker for taxonomy fields
 * (ID-based selection, matching the FilterToolbar behaviour).
 */
const TAXONOMY_ID_OPERATORS: FilterOperator[] = [ '=', '!=' ];

/**
 * Returns the list of operators applicable to a given field type.
 */
export function getOperatorsForField( field: Field ): FilterOperator[] {
	return ( Object.keys( OPERATOR_META ) as FilterOperator[] ).filter( ( op ) => {
		const meta = OPERATOR_META[ op ];
		if ( ! meta.types || meta.types.length === 0 ) return true;
		return meta.types.includes( field.type );
	} );
}

// ── Props ─────────────────────────────────────────────────────────────────────

export interface FilterConditionRowProps {
	condition: FilterCondition;
	path: string;
	fields: Field[];
	onUpdate: ( path: string, patch: Partial< FilterCondition > ) => void;
	onRemove: ( path: string ) => void;
}

// ── Component ─────────────────────────────────────────────────────────────────

export function FilterConditionRow( {
	condition,
	path,
	fields,
	onUpdate,
	onRemove,
}: FilterConditionRowProps ): JSX.Element {
	const [ selectedTermName, setSelectedTermName ] = useState( '' );

	// Reset selected term name when the field changes (new field = no previous selection).
	useEffect( () => {
		setSelectedTermName( '' );
	}, [ condition.field ] );

	const filterableFields = fields.filter( ( f ) => f.filterable );
	const selectedField = fields.find( ( f ) => f.key === condition.field ) ?? null;
	const availableOperators = selectedField ? getOperatorsForField( selectedField ) : [];
	const operatorMeta = OPERATOR_META[ condition.operator ] ?? { valueMode: 'single', label: condition.operator };

	// Normalize value to string for display in single-value inputs.
	const singleValue = Array.isArray( condition.value )
		? ( condition.value as string[] ).join( ',' )
		: ( condition.value ?? '' );

	// For BETWEEN: two-element array or comma-split string.
	const rangeValues: [ string, string ] = ( () => {
		if ( Array.isArray( condition.value ) && condition.value.length >= 2 ) {
			return [ String( condition.value[ 0 ] ), String( condition.value[ 1 ] ) ];
		}
		if ( typeof condition.value === 'string' && condition.value.includes( ',' ) ) {
			const parts = condition.value.split( ',', 2 );
			return [ parts[ 0 ].trim(), parts[ 1 ].trim() ];
		}
		return [ '', '' ];
	} )();

	const handleFieldChange = ( e: React.ChangeEvent< HTMLSelectElement > ) => {
		onUpdate( path, { field: e.target.value } );
	};

	const handleOperatorChange = ( e: React.ChangeEvent< HTMLSelectElement > ) => {
		const newOperator = e.target.value as FilterOperator;
		const newMeta = OPERATOR_META[ newOperator ] ?? { valueMode: 'single' };
		const oldMeta = OPERATOR_META[ condition.operator ] ?? { valueMode: 'single' };

		// Reset the value when switching between incompatible value modes to
		// prevent stale array values (from BETWEEN) from being sent for single
		// operators like = — which would cause zero results on the backend.
		if ( newMeta.valueMode !== oldMeta.valueMode ) {
			onUpdate( path, { operator: newOperator, value: '' } );
		} else {
			onUpdate( path, { operator: newOperator } );
		}
	};

	const handleSingleValueChange = ( e: React.ChangeEvent< HTMLInputElement | HTMLTextAreaElement > ) => {
		onUpdate( path, { value: e.target.value } );
	};

	const handleRangeMinChange = ( e: React.ChangeEvent< HTMLInputElement > ) => {
		onUpdate( path, { value: [ e.target.value, rangeValues[ 1 ] ] } );
	};

	const handleRangeMaxChange = ( e: React.ChangeEvent< HTMLInputElement > ) => {
		onUpdate( path, { value: [ rangeValues[ 0 ], e.target.value ] } );
	};

	// Called when TaxonomyTermPicker selects a term.
	const handleTermSelect = ( term: TaxonomyTermDetail ) => {
		// Store the term_id as a string — backend detects numeric value → term ID path.
		onUpdate( path, { value: String( term.id ) } );
		setSelectedTermName( term.name );
	};

	const isNumeric =
		selectedField?.type === 'number' ||
		selectedField?.type === 'price' ||
		selectedField?.type === 'integer';

	const isTaxonomy = selectedField?.type === 'taxonomy';
	const isTaxonomyIdOp = TAXONOMY_ID_OPERATORS.includes( condition.operator );

	return (
		<div className="iwbe-filter-condition-row">
			{ /* Field selector */ }
			<select
				className="iwbe-condition-field-select"
				value={ condition.field }
				onChange={ handleFieldChange }
				aria-label={ __( 'Filter field', 'ihumbak-woo-bulk-edit' ) }
			>
				{ filterableFields.map( ( f ) => (
					<option key={ f.key } value={ f.key }>
						{ f.label }
					</option>
				) ) }
			</select>

			{ /* Operator selector */ }
			<select
				className="iwbe-condition-operator-select"
				value={ condition.operator }
				onChange={ handleOperatorChange }
				aria-label={ __( 'Filter operator', 'ihumbak-woo-bulk-edit' ) }
			>
				{ availableOperators.map( ( op ) => (
					<option key={ op } value={ op }>
						{ OPERATOR_META[ op ]?.label ?? op }
					</option>
				) ) }
			</select>

			{ /* Value input — branches on operator's valueMode and field type */ }

			{ /* Taxonomy fields with = / != use TaxonomyTermPicker (ID-based, like FilterToolbar) */ }
			{ operatorMeta.valueMode !== 'none' && isTaxonomy && isTaxonomyIdOp && (
				<div className="iwbe-condition-taxonomy-picker">
					<TaxonomyTermPicker
						fieldKey={ condition.field }
						selectedLabel={ selectedTermName }
						onSelect={ handleTermSelect }
						onCancel={ () => {} }
					/>
					{ /* Show the currently selected term ID (if any) so the user knows a term is set */ }
					{ singleValue !== '' && (
						<span className="iwbe-condition-term-id-badge">
							{ __( 'Term ID:', 'ihumbak-woo-bulk-edit' ) }{ ' ' }
							{ singleValue }
						</span>
					) }
				</div>
			) }

			{ operatorMeta.valueMode === 'multi' && (
				<textarea
					className="iwbe-tag-input"
					value={ singleValue }
					onChange={ handleSingleValueChange }
					placeholder={ __( 'value1, value2, …', 'ihumbak-woo-bulk-edit' ) }
					rows={ 2 }
					aria-label={ __( 'Filter values (comma-separated)', 'ihumbak-woo-bulk-edit' ) }
				/>
			) }

			{ operatorMeta.valueMode === 'range' && (
				<span className="iwbe-condition-range">
					<input
						type={ isNumeric ? 'number' : 'text' }
						className="iwbe-condition-range-min"
						value={ rangeValues[ 0 ] }
						onChange={ handleRangeMinChange }
						placeholder={ __( 'min', 'ihumbak-woo-bulk-edit' ) }
						aria-label={ __( 'Range minimum', 'ihumbak-woo-bulk-edit' ) }
					/>
					<span className="iwbe-range-sep">—</span>
					<input
						type={ isNumeric ? 'number' : 'text' }
						className="iwbe-condition-range-max"
						value={ rangeValues[ 1 ] }
						onChange={ handleRangeMaxChange }
						placeholder={ __( 'max', 'ihumbak-woo-bulk-edit' ) }
						aria-label={ __( 'Range maximum', 'ihumbak-woo-bulk-edit' ) }
					/>
				</span>
			) }

			{ /* Plain text/number input for non-taxonomy single-value conditions */ }
			{ operatorMeta.valueMode === 'single' && ! ( isTaxonomy && isTaxonomyIdOp ) && (
				<input
					type={ isNumeric ? 'number' : 'text' }
					className="iwbe-condition-value-input"
					value={ singleValue }
					onChange={ handleSingleValueChange }
					placeholder={ __( 'value', 'ihumbak-woo-bulk-edit' ) }
					aria-label={ __( 'Filter value', 'ihumbak-woo-bulk-edit' ) }
				/>
			) }

			{ /* Remove button */ }
			<button
				type="button"
				className="iwbe-condition-remove-btn"
				onClick={ () => onRemove( path ) }
				aria-label={ __( 'Remove condition', 'ihumbak-woo-bulk-edit' ) }
			>
				×
			</button>
		</div>
	);
}
