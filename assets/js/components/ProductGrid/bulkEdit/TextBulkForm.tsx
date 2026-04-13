import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field } from '@/types/api';
import type { TextCaseMode, TextOperation } from '../bulkOperations';

interface TextBulkFormProps {
	field: Field;
	onChange: ( op: TextOperation | null ) => void;
}

type Mode = TextOperation[ 'type' ];

const BASE_MODES: { value: Mode; label: string }[] = [
	{ value: 'set', label: __( 'Set value', 'ihumbak-woo-bulk-edit' ) },
	{
		value: 'search_replace',
		label: __( 'Search & Replace', 'ihumbak-woo-bulk-edit' ),
	},
	{ value: 'append', label: __( 'Append', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'prepend', label: __( 'Prepend', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'case', label: __( 'Change case', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'clear', label: __( 'Clear value', 'ihumbak-woo-bulk-edit' ) },
];

const CASE_MODES: { value: TextCaseMode; label: string }[] = [
	{ value: 'upper', label: __( 'UPPERCASE', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'lower', label: __( 'lowercase', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'title', label: __( 'Title Case', 'ihumbak-woo-bulk-edit' ) },
	{
		value: 'sentence',
		label: __( 'Sentence case', 'ihumbak-woo-bulk-edit' ),
	},
];

export function TextBulkForm( {
	field,
	onChange,
}: TextBulkFormProps ): JSX.Element {
	const isSelect = field.type === 'select';
	// For select fields, only "Set value" / "Clear" make sense
	const modes = isSelect
		? BASE_MODES.filter( ( m ) => m.value === 'set' || m.value === 'clear' )
		: BASE_MODES;

	const [ mode, setMode ] = useState< Mode >( 'set' );
	const [ setValue, setSetValue ] = useState( '' );
	const [ searchValue, setSearchValue ] = useState( '' );
	const [ replaceValue, setReplaceValue ] = useState( '' );
	const [ caseSensitive, setCaseSensitive ] = useState( false );
	const [ affixValue, setAffixValue ] = useState( '' );
	const [ caseMode, setCaseMode ] = useState< TextCaseMode >( 'upper' );

	const emitFromState = ( overrides?: {
		mode?: Mode;
		setValue?: string;
		searchValue?: string;
		replaceValue?: string;
		caseSensitive?: boolean;
		affixValue?: string;
		caseMode?: TextCaseMode;
	} ): void => {
		const m = overrides?.mode ?? mode;
		const s = overrides?.setValue ?? setValue;
		const sv = overrides?.searchValue ?? searchValue;
		const rv = overrides?.replaceValue ?? replaceValue;
		const cs = overrides?.caseSensitive ?? caseSensitive;
		const av = overrides?.affixValue ?? affixValue;
		const cm = overrides?.caseMode ?? caseMode;

		switch ( m ) {
			case 'set':
				onChange( { type: 'set', value: s } );
				break;
			case 'clear':
				onChange( { type: 'clear' } );
				break;
			case 'search_replace':
				if ( sv === '' ) {
					onChange( null );
				} else {
					onChange( {
						type: 'search_replace',
						search: sv,
						replace: rv,
						caseSensitive: cs,
					} );
				}
				break;
			case 'append':
				if ( av === '' ) {
					onChange( null );
				} else {
					onChange( { type: 'append', value: av } );
				}
				break;
			case 'prepend':
				if ( av === '' ) {
					onChange( null );
				} else {
					onChange( { type: 'prepend', value: av } );
				}
				break;
			case 'case':
				onChange( { type: 'case', mode: cm } );
				break;
		}
	};

	const handleModeChange = ( next: Mode ): void => {
		setMode( next );
		emitFromState( { mode: next } );
	};

	const renderSetValueInput = (): JSX.Element => {
		if ( isSelect ) {
			const optionEntries = Object.entries( field.options );
			return (
				<label className="iwbe-bulk-field">
					<span>{ __( 'New value', 'ihumbak-woo-bulk-edit' ) }</span>
					<select
						value={ setValue }
						onChange={ ( e ) => {
							setSetValue( e.target.value );
							emitFromState( { setValue: e.target.value } );
						} }
					>
						<option value="">
							{ __( '— Select —', 'ihumbak-woo-bulk-edit' ) }
						</option>
						{ optionEntries.map( ( [ key, label ] ) => (
							<option key={ key } value={ key }>
								{ label }
							</option>
						) ) }
					</select>
				</label>
			);
		}
		return (
			<label className="iwbe-bulk-field">
				<span>{ __( 'New value', 'ihumbak-woo-bulk-edit' ) }</span>
				<input
					type="text"
					value={ setValue }
					onChange={ ( e ) => {
						setSetValue( e.target.value );
						emitFromState( { setValue: e.target.value } );
					} }
					autoFocus
				/>
			</label>
		);
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
					{ modes.map( ( m ) => (
						<option key={ m.value } value={ m.value }>
							{ m.label }
						</option>
					) ) }
				</select>
			</label>

			{ mode === 'set' && renderSetValueInput() }

			{ mode === 'search_replace' && (
				<>
					<label className="iwbe-bulk-field">
						<span>
							{ __( 'Search for', 'ihumbak-woo-bulk-edit' ) }
						</span>
						<input
							type="text"
							value={ searchValue }
							onChange={ ( e ) => {
								setSearchValue( e.target.value );
								emitFromState( {
									searchValue: e.target.value,
								} );
							} }
							autoFocus
						/>
					</label>
					<label className="iwbe-bulk-field">
						<span>
							{ __( 'Replace with', 'ihumbak-woo-bulk-edit' ) }
						</span>
						<input
							type="text"
							value={ replaceValue }
							onChange={ ( e ) => {
								setReplaceValue( e.target.value );
								emitFromState( {
									replaceValue: e.target.value,
								} );
							} }
						/>
					</label>
					<label className="iwbe-bulk-checkbox">
						<input
							type="checkbox"
							checked={ caseSensitive }
							onChange={ ( e ) => {
								setCaseSensitive( e.target.checked );
								emitFromState( {
									caseSensitive: e.target.checked,
								} );
							} }
						/>
						<span>
							{ __( 'Case sensitive', 'ihumbak-woo-bulk-edit' ) }
						</span>
					</label>
				</>
			) }

			{ ( mode === 'append' || mode === 'prepend' ) && (
				<label className="iwbe-bulk-field">
					<span>{ __( 'Text', 'ihumbak-woo-bulk-edit' ) }</span>
					<input
						type="text"
						value={ affixValue }
						onChange={ ( e ) => {
							setAffixValue( e.target.value );
							emitFromState( { affixValue: e.target.value } );
						} }
						autoFocus
					/>
				</label>
			) }

			{ mode === 'case' && (
				<label className="iwbe-bulk-field">
					<span>{ __( 'Case mode', 'ihumbak-woo-bulk-edit' ) }</span>
					<select
						value={ caseMode }
						onChange={ ( e ) => {
							const v = e.target.value as TextCaseMode;
							setCaseMode( v );
							emitFromState( { caseMode: v } );
						} }
					>
						{ CASE_MODES.map( ( c ) => (
							<option key={ c.value } value={ c.value }>
								{ c.label }
							</option>
						) ) }
					</select>
				</label>
			) }
		</div>
	);
}
