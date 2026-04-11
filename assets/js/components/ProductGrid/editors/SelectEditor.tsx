import { useRef, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { STATUS_LABELS } from '../columnFactory';
import type { CellEditorProps } from './CellEditorProps';

function getOptions( field: CellEditorProps[ 'field' ] ): Record< string, string > {
	if ( field.type === 'boolean' ) {
		return {
			'1': __( 'Yes', 'ihumbak-woo-bulk-edit' ),
			'0': __( 'No', 'ihumbak-woo-bulk-edit' ),
		};
	}
	if ( field.key === 'status' ) {
		return STATUS_LABELS;
	}
	return field.options;
}

export function SelectEditor( {
	value,
	field,
	onConfirm,
	onCancel,
}: CellEditorProps ): JSX.Element {
	const selectRef = useRef< HTMLSelectElement >( null );
	const currentValue = value !== null && value !== undefined ? String( value ) : '';
	const options = getOptions( field );

	useEffect( () => {
		selectRef.current?.focus();
	}, [] );

	const handleChange = ( e: React.ChangeEvent< HTMLSelectElement > ) => {
		onConfirm( e.target.value );
	};

	const handleKeyDown = ( e: React.KeyboardEvent ) => {
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			e.stopPropagation();
			onCancel();
		} else if ( e.key === 'Tab' ) {
			e.preventDefault();
			e.stopPropagation();
			onConfirm( ( e.target as HTMLSelectElement ).value );
		}
	};

	return (
		<div className="iwbe-td-editing">
			<select
				ref={ selectRef }
				className="iwbe-cell-editor"
				value={ currentValue }
				onChange={ handleChange }
				onKeyDown={ handleKeyDown }
				onBlur={ () => onCancel() }
			>
				{ Object.entries( options ).map( ( [ key, label ] ) => (
					<option key={ key } value={ key }>
						{ label }
					</option>
				) ) }
			</select>
		</div>
	);
}
