import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import type { TaxonomyOperation } from '../bulkOperations';

interface TaxonomyBulkFormProps {
	onChange: ( op: TaxonomyOperation | null ) => void;
}

type Mode = TaxonomyOperation[ 'type' ];

const MODES: { value: Mode; label: string }[] = [
	{ value: 'add', label: __( 'Add', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'remove', label: __( 'Remove', 'ihumbak-woo-bulk-edit' ) },
	{ value: 'replace', label: __( 'Replace all', 'ihumbak-woo-bulk-edit' ) },
];

/**
 * Parse a user-entered term list of the form "123:Name, 456:Other" or just
 * "Name 1, Name 2" (with synthetic negative IDs). For the initial
 * implementation term resolution against the REST API is deferred, so users
 * pairs term IDs with display names explicitly via the `id:name` syntax.
 * @param input
 */
function parseTermInput( input: string ): {
	terms: { id: number; name: string }[];
	ids: number[];
} {
	const parts = input
		.split( ',' )
		.map( ( p ) => p.trim() )
		.filter( ( p ) => p.length > 0 );

	const terms: { id: number; name: string }[] = [];
	const ids: number[] = [];

	for ( const part of parts ) {
		const [ rawId, ...rest ] = part.split( ':' );
		const id = parseInt( rawId, 10 );
		if ( Number.isNaN( id ) ) {
			continue;
		}
		ids.push( id );
		terms.push( { id, name: rest.join( ':' ).trim() || `#${ id }` } );
	}

	return { terms, ids };
}

export function TaxonomyBulkForm( {
	onChange,
}: TaxonomyBulkFormProps ): JSX.Element {
	const [ mode, setMode ] = useState< Mode >( 'add' );
	const [ input, setInput ] = useState( '' );

	const emit = ( nextMode: Mode, nextInput: string ): void => {
		const parsed = parseTermInput( nextInput );
		if ( parsed.terms.length === 0 && nextMode !== 'replace' ) {
			onChange( null );
			return;
		}
		if ( nextMode === 'add' ) {
			onChange( { type: 'add', terms: parsed.terms } );
		} else if ( nextMode === 'remove' ) {
			onChange( { type: 'remove', termIds: parsed.ids } );
		} else {
			onChange( { type: 'replace', terms: parsed.terms } );
		}
	};

	const handleModeChange = ( next: Mode ): void => {
		setMode( next );
		emit( next, input );
	};

	const handleInputChange = ( next: string ): void => {
		setInput( next );
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
			<label className="iwbe-bulk-field">
				<span>
					{ __(
						'Terms (ID:Name, comma separated)',
						'ihumbak-woo-bulk-edit'
					) }
				</span>
				<input
					type="text"
					value={ input }
					onChange={ ( e ) => handleInputChange( e.target.value ) }
					placeholder="15:Shoes, 22:Apparel"
					autoFocus
				/>
			</label>
			<p className="iwbe-bulk-hint">
				{ __(
					'Note: taxonomy bulk edits are staged in preview. Saving taxonomy changes is not yet implemented on the backend.',
					'ihumbak-woo-bulk-edit'
				) }
			</p>
		</div>
	);
}
