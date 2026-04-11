import { useRef, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useEditingStore } from '@/store';
import type { CellEditorProps } from './CellEditorProps';

export function TextEditor( {
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

	const inputRef = useRef< HTMLInputElement | HTMLTextAreaElement >( null );

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
		if ( e.key === 'Enter' && ! e.shiftKey ) {
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

	const isTextarea = field.type === 'textarea';
	const className = `iwbe-cell-editor${
		validationError ? ' iwbe-cell-editor--invalid' : ''
	}`;

	if ( isTextarea ) {
		return (
			<div className="iwbe-td-editing">
				<textarea
					ref={ inputRef as React.RefObject< HTMLTextAreaElement > }
					className={ className }
					value={ localValue }
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

	return (
		<div className="iwbe-td-editing">
			<input
				ref={ inputRef as React.RefObject< HTMLInputElement > }
				type="text"
				className={ className }
				value={ localValue }
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
