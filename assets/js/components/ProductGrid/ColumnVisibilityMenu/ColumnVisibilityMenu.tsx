import { useState, useRef, useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field } from '@/types/api';
import { useColumnVisibilityStore } from '@/store/useColumnVisibilityStore';
import { useColumnVisibility } from '@/hooks/useColumnVisibility';
import { ColumnVisibilityDialog } from './ColumnVisibilityDialog';

export interface ColumnVisibilityMenuProps {
	fields: Field[];
}

/**
 * Toolbar button + popover dialog for managing column visibility.
 *
 * The button shows how many columns are currently visible. Clicking it opens
 * a dialog with search, grouped checkboxes, and footer actions.
 */
export function ColumnVisibilityMenu( {
	fields,
}: ColumnVisibilityMenuProps ): JSX.Element {
	const [ isOpen, setIsOpen ] = useState( false );
	const wrapperRef = useRef< HTMLDivElement >( null );

	const { hidden, persist } = useColumnVisibility();
	const toggle = useColumnVisibilityStore( ( s ) => s.toggle );
	const selectAll = useColumnVisibilityStore( ( s ) => s.selectAll );
	const deselectAll = useColumnVisibilityStore( ( s ) => s.deselectAll );
	const resetToDefault = useColumnVisibilityStore( ( s ) => s.resetToDefault );

	const allColumnIds = fields.map( ( f ) => f.key );

	// Close on outside click
	useEffect( () => {
		function handleClickOutside( event: MouseEvent ) {
			if (
				wrapperRef.current &&
				! wrapperRef.current.contains( event.target as Node )
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

	// Close on Escape
	useEffect( () => {
		function handleKeyDown( event: KeyboardEvent ) {
			if ( event.key === 'Escape' ) {
				setIsOpen( false );
			}
		}

		if ( isOpen ) {
			document.addEventListener( 'keydown', handleKeyDown );
		}

		return () => {
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ isOpen ] );

	const handleToggle = useCallback(
		( columnId: string ) => {
			toggle( columnId );
			// Read fresh state from store after the synchronous toggle update.
			persist( useColumnVisibilityStore.getState().hidden );
		},
		[ toggle, persist ]
	);

	const handleSelectAll = useCallback( () => {
		selectAll();
		persist( new Set< string >() );
	}, [ selectAll, persist ] );

	const handleDeselectAll = useCallback( () => {
		deselectAll( allColumnIds );
		const newHidden = new Set(
			allColumnIds.filter(
				( id ) => id !== 'select' && id !== 'id'
			)
		);
		persist( newHidden );
	}, [ deselectAll, allColumnIds, persist ] );

	const handleResetToDefault = useCallback( () => {
		resetToDefault( allColumnIds );
		// resetToDefault updates Zustand synchronously — read fresh state immediately.
		persist( useColumnVisibilityStore.getState().hidden );
	}, [ resetToDefault, allColumnIds, persist ] );

	return (
		<div className="iwbe-column-visibility-menu" ref={ wrapperRef }>
			<button
				type="button"
				className="iwbe-column-visibility-btn"
				onClick={ () => setIsOpen( ( prev ) => ! prev ) }
				aria-expanded={ isOpen }
				aria-haspopup="dialog"
				aria-label={ __( 'Manage column visibility', 'ihumbak-woo-bulk-edit' ) }
			>
				{ __( 'Columns', 'ihumbak-woo-bulk-edit' ) }
				<span className="iwbe-column-visibility-caret" aria-hidden="true">
					▾
				</span>
			</button>

			{ isOpen && (
				<ColumnVisibilityDialog
					fields={ fields }
					hidden={ hidden }
					onToggle={ handleToggle }
					onSelectAll={ handleSelectAll }
					onDeselectAll={ handleDeselectAll }
					onResetToDefault={ handleResetToDefault }
				/>
			) }
		</div>
	);
}
