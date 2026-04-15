import { useState, useCallback, useRef, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import {
	useSavedFilters,
	useUpdateSavedFilter,
	useDeleteSavedFilter,
} from '@/hooks/useSavedFilters';
import { useSaveCurrentFilter, isEffectivelyEmpty } from '@/hooks/useSaveCurrentFilter';
import { useRecentFiltersStore } from '@/store/useRecentFiltersStore';
import { useFiltersStore } from '@/store/useFiltersStore';
import { SaveFilterDialog } from './SaveFilterDialog';
import type { SavedFilter, SavedFilterDefinition } from '@/types/api';

export interface SavedFiltersMenuProps {
	onApplyPreset: ( definition: SavedFilterDefinition ) => void;
}

export function SavedFiltersMenu( {
	onApplyPreset,
}: SavedFiltersMenuProps ): JSX.Element {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ showSaveDialog, setShowSaveDialog ] = useState( false );
	const [ editingFilter, setEditingFilter ] = useState< SavedFilter | null >( null );
	const [ saveError, setSaveError ] = useState< string | undefined >( undefined );
	const dropdownRef = useRef< HTMLDivElement >( null );

	// Read current filter state directly from Zustand — avoids stale prop caching.
	const { root, searchQuery } = useFiltersStore();

	const { data: savedFiltersData } = useSavedFilters();
	const updateMutation = useUpdateSavedFilter();
	const deleteMutation = useDeleteSavedFilter();
	const { save: saveCurrentFilter, isSaving } = useSaveCurrentFilter();

	const { recents, touch } = useRecentFiltersStore();

	const myFilters = ( savedFiltersData?.items ?? [] ).filter( ( f ) => ! f.is_shared );
	const teamFilters = ( savedFiltersData?.items ?? [] ).filter( ( f ) => f.is_shared );

	// Disable "Save current filter" when nothing meaningful is active.
	// isEffectivelyEmpty strips empty nested groups before checking, so a root
	// with only empty groups (accidentally added) is treated as empty.
	const isSaveDisabled = isEffectivelyEmpty( root ) && searchQuery.trim() === '';

	// Close on outside click
	useEffect( () => {
		function handleClickOutside( event: MouseEvent ) {
			if (
				dropdownRef.current &&
				! dropdownRef.current.contains( event.target as Node )
			) {
				setIsOpen( false );
			}
		}
		if ( isOpen ) {
			document.addEventListener( 'mousedown', handleClickOutside );
		}
		return () => {
			document.removeEventListener( 'mousedown', handleClickOutside );
		};
	}, [ isOpen ] );

	const handleApply = useCallback(
		( filter: SavedFilter ) => {
			onApplyPreset( filter.definition );
			touch( {
				id: filter.id,
				name: filter.name,
				definition: filter.definition,
				usedAt: Date.now(),
			} );
			setIsOpen( false );
		},
		[ onApplyPreset, touch ]
	);

	const handleApplyRecent = useCallback(
		( entry: ( typeof recents )[ 0 ] ) => {
			onApplyPreset( entry.definition );
			touch( { ...entry, usedAt: Date.now() } );
			setIsOpen( false );
		},
		[ onApplyPreset, touch ]
	);

	const handleSave = useCallback(
		async ( name: string, isShared: boolean ) => {
			setSaveError( undefined );
			try {
				await saveCurrentFilter( { name, isShared, root, search: searchQuery } );
				setShowSaveDialog( false );
				setIsOpen( false );
			} catch ( err ) {
				if ( err instanceof Error ) {
					setSaveError( err.message );
				}
			}
		},
		[ saveCurrentFilter, root, searchQuery ]
	);

	const handleRename = useCallback(
		async ( filter: SavedFilter, newName: string ) => {
			try {
				await updateMutation.mutateAsync( {
					id: filter.id,
					payload: { name: newName },
				} );
				setEditingFilter( null );
			} catch ( err ) {
				// silently ignore for now; could surface error inline
			}
		},
		[ updateMutation ]
	);

	const handleDelete = useCallback(
		async ( filter: SavedFilter ) => {
			await deleteMutation.mutateAsync( filter.id );
		},
		[ deleteMutation ]
	);

	return (
		<div className="iwbe-saved-filters" ref={ dropdownRef }>
			<button
				type="button"
				className="iwbe-saved-filters-btn"
				onClick={ () => setIsOpen( ( prev ) => ! prev ) }
				aria-expanded={ isOpen }
				aria-haspopup="true"
			>
				{ __( 'Saved filters', 'ihumbak-woo-bulk-edit' ) }
				<span className="iwbe-saved-filters-caret" aria-hidden="true">
					▾
				</span>
			</button>

			{ isOpen && (
				<div className="iwbe-saved-filters-dropdown" role="menu">
					{ recents.length > 0 && (
						<section className="iwbe-saved-filters-section">
							<div className="iwbe-saved-filters-section-title">
								{ __( 'Recently used', 'ihumbak-woo-bulk-edit' ) }
							</div>
							{ recents.map( ( entry, idx ) => (
								<button
									key={ `recent-${ idx }` }
									type="button"
									className="iwbe-saved-filters-item"
									role="menuitem"
									onClick={ () => handleApplyRecent( entry ) }
								>
									{ entry.name }
								</button>
							) ) }
						</section>
					) }

					{ myFilters.length > 0 && (
						<section className="iwbe-saved-filters-section">
							<div className="iwbe-saved-filters-section-title">
								{ __( 'My filters', 'ihumbak-woo-bulk-edit' ) }
							</div>
							{ myFilters.map( ( filter ) => (
								<div key={ filter.id } className="iwbe-saved-filters-item-row">
									{ editingFilter?.id === filter.id ? (
										<input
											type="text"
											className="iwbe-saved-filters-rename-input"
											defaultValue={ filter.name }
											autoFocus
											onBlur={ ( e ) =>
												handleRename( filter, e.target.value )
											}
											onKeyDown={ ( e ) => {
												if ( e.key === 'Enter' ) {
													handleRename(
														filter,
														( e.target as HTMLInputElement ).value
													);
												} else if ( e.key === 'Escape' ) {
													setEditingFilter( null );
												}
											} }
										/>
									) : (
										<button
											type="button"
											className="iwbe-saved-filters-item"
											role="menuitem"
											onClick={ () => handleApply( filter ) }
										>
											{ filter.name }
										</button>
									) }
									<button
										type="button"
										className="iwbe-saved-filters-rename-btn"
										title={ __( 'Rename', 'ihumbak-woo-bulk-edit' ) }
										onClick={ ( e ) => {
											e.stopPropagation();
											setEditingFilter( filter );
										} }
										aria-label={ __( 'Rename filter', 'ihumbak-woo-bulk-edit' ) }
									>
										✎
									</button>
									<button
										type="button"
										className="iwbe-saved-filters-delete-btn"
										title={ __( 'Delete', 'ihumbak-woo-bulk-edit' ) }
										onClick={ ( e ) => {
											e.stopPropagation();
											handleDelete( filter );
										} }
										aria-label={ __( 'Delete filter', 'ihumbak-woo-bulk-edit' ) }
									>
										×
									</button>
								</div>
							) ) }
						</section>
					) }

					{ teamFilters.length > 0 && (
						<section className="iwbe-saved-filters-section">
							<div className="iwbe-saved-filters-section-title">
								{ __( 'Team filters', 'ihumbak-woo-bulk-edit' ) }
							</div>
							{ teamFilters.map( ( filter ) => (
								<div key={ filter.id } className="iwbe-saved-filters-item-row">
									<button
										type="button"
										className="iwbe-saved-filters-item"
										role="menuitem"
										onClick={ () => handleApply( filter ) }
									>
										{ filter.name }
									</button>
									{ iwbeData.canManageSharedFilters && (
										<>
											<button
												type="button"
												className="iwbe-saved-filters-rename-btn"
												title={ __( 'Rename', 'ihumbak-woo-bulk-edit' ) }
												onClick={ ( e ) => {
													e.stopPropagation();
													setEditingFilter( filter );
												} }
												aria-label={ __(
													'Rename filter',
													'ihumbak-woo-bulk-edit'
												) }
											>
												✎
											</button>
											<button
												type="button"
												className="iwbe-saved-filters-delete-btn"
												title={ __( 'Delete', 'ihumbak-woo-bulk-edit' ) }
												onClick={ ( e ) => {
													e.stopPropagation();
													handleDelete( filter );
												} }
												aria-label={ __(
													'Delete filter',
													'ihumbak-woo-bulk-edit'
												) }
											>
												×
											</button>
										</>
									) }
								</div>
							) ) }
						</section>
					) }

					{ myFilters.length === 0 &&
						teamFilters.length === 0 &&
						recents.length === 0 && (
							<div className="iwbe-saved-filters-empty">
								{ __( 'No saved filters yet.', 'ihumbak-woo-bulk-edit' ) }
							</div>
						) }

					<div className="iwbe-saved-filters-footer">
						<button
							type="button"
							className="iwbe-saved-filters-save-btn button"
							disabled={ isSaveDisabled }
							onClick={ () => {
								setShowSaveDialog( true );
								setIsOpen( false );
							} }
						>
							{ __( 'Save current filter', 'ihumbak-woo-bulk-edit' ) }
						</button>
					</div>
				</div>
			) }

			{ showSaveDialog && (
				<SaveFilterDialog
					onSave={ handleSave }
					onCancel={ () => {
						setShowSaveDialog( false );
						setSaveError( undefined );
					} }
					isSaving={ isSaving }
					errorMessage={ saveError }
				/>
			) }
		</div>
	);
}
