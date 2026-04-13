import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import type { BooleanOperation } from '../bulkOperations';

interface BooleanBulkFormProps {
	onChange: ( op: BooleanOperation ) => void;
}

type Mode = BooleanOperation[ 'type' ];

const MODES: { value: Mode; label: string }[] = [
	{ value: 'set_true', label: __( 'Set to Yes', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'set_false', label: __( 'Set to No', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'toggle', label: __( 'Toggle', 'ihumbak-woo-bulk-edit' ) },
];

export function BooleanBulkForm( {
	onChange,
}: BooleanBulkFormProps ): JSX.Element {
	const [ mode, setMode ] = useState< Mode >( 'set_true' );

	// Emit immediately so Apply is valid without user changing anything
	useEffect( () => {
		onChange( { type: mode } as BooleanOperation );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ mode ] );

	return (
		<div className="iwbe-bulk-form">
			<label className="iwbe-bulk-field">
				<span>{ __( 'Operation', 'ihumbak-woo-bulk-edit' ) }</span>
				<select
					value={ mode }
					onChange={ ( e ) => setMode( e.target.value as Mode ) }
				>
					{ MODES.map( ( m ) => (
						<option key={ m.value } value={ m.value }>
							{ m.label }
						</option>
					) ) }
				</select>
			</label>
		</div>
	);
}
