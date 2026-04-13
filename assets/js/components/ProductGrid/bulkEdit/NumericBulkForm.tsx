import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import type { NumericOperation } from '../bulkOperations';

interface NumericBulkFormProps {
	onChange: ( op: NumericOperation | null ) => void;
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

export function NumericBulkForm( {
	onChange,
}: NumericBulkFormProps ): JSX.Element {
	const [ mode, setMode ] = useState< Mode >( 'set' );
	const [ rawValue, setRawValue ] = useState( '' );

	const emit = ( nextMode: Mode, nextRaw: string ): void => {
		if ( nextMode === 'clear' ) {
			onChange( { type: 'clear' } );
			return;
		}
		const num = parseFloat( nextRaw );
		if ( Number.isNaN( num ) ) {
			onChange( null );
			return;
		}
		switch ( nextMode ) {
			case 'set':
				onChange( { type: 'set', value: num } );
				break;
			case 'increase':
				onChange( { type: 'increase', amount: num } );
				break;
			case 'decrease':
				onChange( { type: 'decrease', amount: num } );
				break;
			case 'increase_pct':
				onChange( { type: 'increase_pct', percent: num } );
				break;
			case 'decrease_pct':
				onChange( { type: 'decrease_pct', percent: num } );
				break;
			case 'round':
				onChange( {
					type: 'round',
					precision: Math.max( 0, Math.trunc( num ) ),
				} );
				break;
		}
	};

	const handleModeChange = ( next: Mode ): void => {
		setMode( next );
		emit( next, rawValue );
	};

	const handleValueChange = ( next: string ): void => {
		setRawValue( next );
		emit( mode, next );
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
