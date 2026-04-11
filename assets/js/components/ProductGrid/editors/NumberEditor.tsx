import { useRef, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useEditingStore } from '@/store';
import type { CellEditorProps } from './CellEditorProps';

function getInputProps( fieldType: string ): {
	step: string;
	min?: string;
} {
	switch ( fieldType ) {
		case 'price':
			return { step: '0.01', min: '0' };
		case 'integer':
			return { step: '1', min: '0' };
		default:
			return { step: 'any' };
	}
}

export function NumberEditor( {
	value,
	field,
	onConfirm,
	onCancel,
	validationError,
}: CellEditorProps ): JSX.Element {
	const setDraftValue = useEditingStore( ( s ) => s.setDraftValue );
	const draftValue = useEditingStore( ( s ) => s.draftValue );

	const [ localValue, setLocalValue ] = useState< string >( () => {
		if ( draftValue !== undefined && draftValue !== null ) {
			return String( draftValue );
		}
		return value !== null && value !== undefined ? String( value ) : '';
	} );

	const inputRef = useRef< HTMLInputElement >( null );

	useEffect( () => {
		const el = inputRef.current;
		if ( el ) {
			el.focus();
			el.select();
		}
	}, [] );

	const handleChange = ( newVal: string ) => {
		setLocalValue( newVal );
		setDraftValue( newVal );
	};

	const handleKeyDown = ( e: React.KeyboardEvent ) => {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			e.stopPropagation();
			onConfirm( localValue );
		} else if ( e.key === 'Escape' ) {
			e.preventDefault();
			e.stopPropagation();
			onCancel();
		} else if ( e.key === 'Tab' ) {
			e.preventDefault();
			e.stopPropagation();
			onConfirm( localValue );
		}
	};

	const { step, min } = getInputProps( field.type );
	const className = `iwbe-cell-editor${
		validationError ? ' iwbe-cell-editor--invalid' : ''
	}`;

	return (
		<div className="iwbe-td-editing">
			<input
				ref={ inputRef }
				type="number"
				className={ className }
				value={ localValue }
				step={ step }
				min={ min }
				onChange={ ( e ) => handleChange( e.target.value ) }
				onKeyDown={ handleKeyDown }
				onBlur={ () => onConfirm( localValue ) }
				title={
					validationError ??
					__( 'Press Enter to save, Esc to cancel', 'ihumbak-woo-bulk-edit' )
				}
			/>
			{ validationError && (
				<span className="iwbe-validation-tooltip">
					{ validationError }
				</span>
			) }
		</div>
	);
}
