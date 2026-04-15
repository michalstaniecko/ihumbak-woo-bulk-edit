import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field } from '@/types/api';
import type { NumericOperation, SpecialEnding } from '../bulkOperations';

export type NumericBase = 'current_sale_price' | 'current_regular_price';

export type { SpecialEnding };

interface NumericBulkFormProps {
	field?: Field;
	onChange: (
		op: NumericOperation | null,
		base: NumericBase,
		specialEnding: SpecialEnding
	) => void;
}

type Mode = NumericOperation[ 'type' ];

const MODES: { value: Mode; label: string }[] = [
	{ value: 'set', label: __( 'Set value', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'increase', label: __( 'Increase by', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'decrease', label: __( 'Decrease by', 'ihumbak-woo-bulk-edit' ) },
	{
		value: 'increase_pct',
		label: __( 'Increase by %', 'ihumbak-woo-bulk-edit' ),
	},
	{
		value: 'decrease_pct',
		label: __( 'Decrease by %', 'ihumbak-woo-bulk-edit' ),
	},
	{ value: 'round', label: __( 'Round to', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'clear', label: __( 'Clear value', 'ihumbak-woo-bulk-edit' ) },
];

const RELATIVE_MODES: Mode[] = [
	'increase',
	'decrease',
	'increase_pct',
	'decrease_pct',
	'round',
];

export function NumericBulkForm( {
	field,
	onChange,
}: NumericBulkFormProps ): JSX.Element {
	const [ mode, setMode ] = useState< Mode >( 'set' );
	const [ rawValue, setRawValue ] = useState( '' );
	const [ base, setBase ] = useState< NumericBase >( 'current_sale_price' );
	const [ specialEnding, setSpecialEnding ] =
		useState< SpecialEnding >( 'none' );

	const showBaseSelector =
		field?.key === 'sale_price' && RELATIVE_MODES.includes( mode );

	// Show the special ending selector only for price fields when not clearing.
	const showSpecialEnding =
		field?.type === 'price' && mode !== 'clear' && mode !== 'round';

	const emit = (
		nextMode: Mode,
		nextRaw: string,
		nextBase: NumericBase,
		nextEnding: SpecialEnding
	): void => {
		if ( nextMode === 'clear' ) {
			onChange( { type: 'clear' }, nextBase, 'none' );
			return;
		}
		const num = parseFloat( nextRaw );
		if ( Number.isNaN( num ) ) {
			onChange( null, nextBase, nextEnding );
			return;
		}
		switch ( nextMode ) {
			case 'set':
				onChange( { type: 'set', value: num }, nextBase, nextEnding );
				break;
			case 'increase':
				onChange(
					{ type: 'increase', amount: num },
					nextBase,
					nextEnding
				);
				break;
			case 'decrease':
				onChange(
					{ type: 'decrease', amount: num },
					nextBase,
					nextEnding
				);
				break;
			case 'increase_pct':
				onChange(
					{ type: 'increase_pct', percent: num },
					nextBase,
					nextEnding
				);
				break;
			case 'decrease_pct':
				onChange(
					{ type: 'decrease_pct', percent: num },
					nextBase,
					nextEnding
				);
				break;
			case 'round':
				onChange(
					{
						type: 'round',
						precision: Math.max( 0, Math.trunc( num ) ),
					},
					nextBase,
					'none'
				);
				break;
		}
	};

	const handleModeChange = ( next: Mode ): void => {
		setMode( next );
		// Suppress special ending for modes where it does not apply, and reset
		// state so it does not silently re-apply when switching back to a numeric mode.
		if ( next === 'clear' || next === 'round' ) {
			setSpecialEnding( 'none' );
		}
		const effectiveEnding =
			next === 'clear' || next === 'round' ? 'none' : specialEnding;
		emit( next, rawValue, base, effectiveEnding );
	};

	const handleValueChange = ( next: string ): void => {
		setRawValue( next );
		emit( mode, next, base, specialEnding );
	};

	const handleBaseChange = ( next: NumericBase ): void => {
		setBase( next );
		emit( mode, rawValue, next, specialEnding );
	};

	const handleSpecialEndingChange = ( next: SpecialEnding ): void => {
		setSpecialEnding( next );
		emit( mode, rawValue, base, next );
	};

	return (
		<div className="iwbe-bulk-form">
			<label className="iwbe-bulk-field">
				<span>{ __( 'Operation', 'ihumbak-woo-bulk-edit' ) }</span>
				<select
					value={ mode }
					onChange={ ( e ) =>
						handleModeChange( e.target.value as Mode )
					}
				>
					{ MODES.map( ( m ) => (
						<option key={ m.value } value={ m.value }>
							{ m.label }
						</option>
					) ) }
				</select>
			</label>

			{ showBaseSelector && (
				<label className="iwbe-bulk-field">
					<span>{ __( 'Base', 'ihumbak-woo-bulk-edit' ) }</span>
					<select
						value={ base }
						onChange={ ( e ) =>
							handleBaseChange( e.target.value as NumericBase )
						}
					>
						<option value="current_sale_price">
							{ __(
								'Current sale price',
								'ihumbak-woo-bulk-edit'
							) }
						</option>
						<option value="current_regular_price">
							{ __(
								'Current regular price',
								'ihumbak-woo-bulk-edit'
							) }
						</option>
					</select>
				</label>
			) }

			{ mode !== 'clear' && (
				<label className="iwbe-bulk-field">
					<span>
						{ mode === 'round'
							? __( 'Decimal places', 'ihumbak-woo-bulk-edit' )
							: __( 'Value', 'ihumbak-woo-bulk-edit' ) }
					</span>
					<input
						type="number"
						step={ mode === 'round' ? '1' : 'any' }
						min={ mode === 'round' ? 0 : undefined }
						value={ rawValue }
						onChange={ ( e ) =>
							handleValueChange( e.target.value )
						}
						autoFocus
					/>
				</label>
			) }

			{ showSpecialEnding && (
				<label className="iwbe-bulk-field">
					<span>
						{ __( 'Round to', 'ihumbak-woo-bulk-edit' ) }
					</span>
					<select
						value={ specialEnding }
						onChange={ ( e ) =>
							handleSpecialEndingChange(
								e.target.value as SpecialEnding
							)
						}
					>
						<option value="none">
							{ __( 'None', 'ihumbak-woo-bulk-edit' ) }
						</option>
						<option value="00">.00</option>
						<option value="90">.90</option>
						<option value="99">.99</option>
						<option value="9_00">x9.00</option>
					</select>
				</label>
			) }
		</div>
	);
}
