import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field } from '@/types/api';
import type { NumericOperation } from '../bulkOperations';

export type NumericBase = 'current_sale_price' | 'current_regular_price';

interface NumericBulkFormProps {
	field?: Field;
	onChange: ( op: NumericOperation | null, base: NumericBase ) => void;
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

	const showBaseSelector =
		field?.key === 'sale_price' && RELATIVE_MODES.includes( mode );

	const emit = (
		nextMode: Mode,
		nextRaw: string,
		nextBase: NumericBase
	): void => {
		if ( nextMode === 'clear' ) {
			onChange( { type: 'clear' }, nextBase );
			return;
		}
		const num = parseFloat( nextRaw );
		if ( Number.isNaN( num ) ) {
			onChange( null, nextBase );
			return;
		}
		switch ( nextMode ) {
			case 'set':
				onChange( { type: 'set', value: num }, nextBase );
				break;
			case 'increase':
				onChange( { type: 'increase', amount: num }, nextBase );
				break;
			case 'decrease':
				onChange( { type: 'decrease', amount: num }, nextBase );
				break;
			case 'increase_pct':
				onChange( { type: 'increase_pct', percent: num }, nextBase );
				break;
			case 'decrease_pct':
				onChange( { type: 'decrease_pct', percent: num }, nextBase );
				break;
			case 'round':
				onChange(
					{
						type: 'round',
						precision: Math.max( 0, Math.trunc( num ) ),
					},
					nextBase
				);
				break;
		}
	};

	const handleModeChange = ( next: Mode ): void => {
		setMode( next );
		emit( next, rawValue, base );
	};

	const handleValueChange = ( next: string ): void => {
		setRawValue( next );
		emit( mode, next, base );
	};

	const handleBaseChange = ( next: NumericBase ): void => {
		setBase( next );
		emit( mode, rawValue, next );
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
		</div>
	);
}
