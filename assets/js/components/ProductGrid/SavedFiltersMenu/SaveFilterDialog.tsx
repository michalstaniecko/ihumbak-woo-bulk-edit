import { useState, useCallback } from 'react';
import { __ } from '@wordpress/i18n';

interface SaveFilterDialogProps {
	onSave: ( name: string, isShared: boolean ) => void;
	onCancel: () => void;
	isSaving: boolean;
	errorMessage?: string;
}

export function SaveFilterDialog( {
	onSave,
	onCancel,
	isSaving,
	errorMessage,
}: SaveFilterDialogProps ): JSX.Element {
	const [ name, setName ] = useState( '' );
	const [ isShared, setIsShared ] = useState( false );

	const canManageShared =
		typeof iwbeData !== 'undefined' && iwbeData.canManageSharedFilters;

	const handleSubmit = useCallback(
		( e: React.FormEvent ) => {
			e.preventDefault();
			if ( name.trim() ) {
				onSave( name.trim(), isShared );
			}
		},
		[ name, isShared, onSave ]
	);

	return (
		<div className="iwbe-save-filter-dialog" role="dialog" aria-modal="true">
			<div className="iwbe-save-filter-dialog-overlay" onClick={ onCancel } />
			<div className="iwbe-save-filter-dialog-content">
				<h3 className="iwbe-save-filter-dialog-title">
					{ __( 'Save current filter', 'ihumbak-woo-bulk-edit' ) }
				</h3>

				<form onSubmit={ handleSubmit }>
					<label className="iwbe-save-filter-label">
						{ __( 'Name', 'ihumbak-woo-bulk-edit' ) }
						<input
							type="text"
							className="iwbe-save-filter-name-input"
							value={ name }
							onChange={ ( e ) => setName( e.target.value ) }
							maxLength={ 191 }
							required
							autoFocus
						/>
					</label>

					{ canManageShared && (
						<label className="iwbe-save-filter-share-label">
							<input
								type="checkbox"
								checked={ isShared }
								onChange={ ( e ) => setIsShared( e.target.checked ) }
							/>
							{ __( 'Share with team', 'ihumbak-woo-bulk-edit' ) }
						</label>
					) }

					{ errorMessage && (
						<p className="iwbe-save-filter-error" role="alert">
							{ errorMessage }
						</p>
					) }

					<div className="iwbe-save-filter-actions">
						<button
							type="submit"
							className="button button-primary"
							disabled={ isSaving || ! name.trim() }
						>
							{ isSaving
								? __( 'Saving…', 'ihumbak-woo-bulk-edit' )
								: __( 'Save', 'ihumbak-woo-bulk-edit' ) }
						</button>
						<button
							type="button"
							className="button"
							onClick={ onCancel }
							disabled={ isSaving }
						>
							{ __( 'Cancel', 'ihumbak-woo-bulk-edit' ) }
						</button>
					</div>
				</form>
			</div>
		</div>
	);
}
