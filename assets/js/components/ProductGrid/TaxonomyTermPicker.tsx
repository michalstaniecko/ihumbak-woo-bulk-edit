import { useState, useCallback, useRef, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useTaxonomyTerms } from '@/hooks/useTaxonomyTerms';
import type { TaxonomyTermDetail } from '@/types/api';

interface TaxonomyTermPickerProps {
	fieldKey: string;
	/** Name of the currently selected term; when set the panel starts closed and the input shows this label. */
	selectedLabel?: string;
	onSelect: ( term: TaxonomyTermDetail ) => void;
	onCancel: () => void;
}

/**
 * Searchable combobox that fetches real WP terms for a taxonomy field.
 *
 * - Input with 200ms debounced search
 * - ↑↓/Enter/Esc keyboard navigation
 * - Loading, empty, and error states
 * - Uses onMouseDown (not onClick) for item selection to beat outside-click handlers
 * - Panel rendered via the native HTML Popover API so it escapes all
 *   overflow:hidden / overflow:auto ancestor clipping (top-layer).
 */
export function TaxonomyTermPicker( {
	fieldKey,
	selectedLabel = '',
	onSelect,
	onCancel,
}: TaxonomyTermPickerProps ): JSX.Element {
	const [ search, setSearch ] = useState( '' );
	const [ debouncedSearch, setDebouncedSearch ] = useState( '' );
	const [ activeIndex, setActiveIndex ] = useState( -1 );
	// Panel starts closed when a term is already selected (e.g. editing a saved filter).
	const [ isOpen, setIsOpen ] = useState( ! selectedLabel );

	const inputRef = useRef< HTMLInputElement >( null );
	const debounceRef = useRef< ReturnType< typeof setTimeout > | null >( null );
	const anchorRef = useRef< HTMLDivElement >( null );
	const panelRef = useRef< HTMLDivElement >( null );

	// Debounce search input at 200ms.
	const handleSearchChange = useCallback(
		( e: React.ChangeEvent< HTMLInputElement > ) => {
			const value = e.target.value;
			setSearch( value );
			setActiveIndex( -1 );

			if ( debounceRef.current ) {
				clearTimeout( debounceRef.current );
			}
			debounceRef.current = setTimeout( () => {
				setDebouncedSearch( value );
			}, 200 );
		},
		[]
	);

	// Cleanup debounce timer on unmount.
	useEffect( () => {
		return () => {
			if ( debounceRef.current ) {
				clearTimeout( debounceRef.current );
			}
		};
	}, [] );

	// Autofocus the input on mount.
	useEffect( () => {
		inputRef.current?.focus();
	}, [] );

	const { isLoading, isError, data } = useTaxonomyTerms(
		fieldKey,
		debouncedSearch,
		true
	);

	const items = data?.items ?? [];

	// Compute position of the popover panel to align it below the anchor.
	// Uses position:fixed so coordinates are viewport-relative — no scroll offset needed.
	const positionPanel = useCallback( () => {
		const panel = panelRef.current;
		const anchor = anchorRef.current;
		if ( ! panel || ! anchor ) return;
		const r = anchor.getBoundingClientRect();
		panel.style.top = `${ r.bottom + 2 }px`;
		panel.style.left = `${ r.left }px`;
		panel.style.width = `${ r.width }px`;
	}, [] );

	// Open/close the popover in sync with `isOpen` state.
	useEffect( () => {
		const panel = panelRef.current;
		if ( ! panel ) return;

		if ( isOpen ) {
			if ( typeof panel.showPopover !== 'function' ) return;
			positionPanel();
			try {
				panel.showPopover();
			} catch {
				// already open — ignore
			}
		} else {
			if ( typeof panel.hidePopover !== 'function' ) return;
			try {
				panel.hidePopover();
			} catch {
				// already closed — ignore
			}
		}

		return () => {
			if ( typeof panel.hidePopover === 'function' ) {
				try {
					panel.hidePopover();
				} catch {
					// already closed — ignore
				}
			}
		};
	}, [ isOpen, positionPanel ] );

	// Reposition on scroll (capture phase catches modal body scroll) and resize.
	useEffect( () => {
		const onReflow = () => positionPanel();
		window.addEventListener( 'scroll', onReflow, true );
		window.addEventListener( 'resize', onReflow );
		return () => {
			window.removeEventListener( 'scroll', onReflow, true );
			window.removeEventListener( 'resize', onReflow );
		};
	}, [ positionPanel ] );

	// Reposition when panel content height changes (loading → items, etc.).
	useEffect( () => {
		positionPanel();
	}, [ items.length, isLoading, isError, positionPanel ] );

	// Sync with browser-native popover dismiss (e.g. Esc key at browser level).
	useEffect( () => {
		const panel = panelRef.current;
		if ( ! panel ) return;
		const onToggle = ( e: Event ): void => {
			const te = e as ToggleEvent;
			if ( te.newState === 'closed' ) onCancel();
		};
		panel.addEventListener( 'toggle', onToggle );
		return () => panel.removeEventListener( 'toggle', onToggle );
	}, [ onCancel ] );

	// Close the panel and propagate selection upward.
	const handleSelect = useCallback(
		( term: TaxonomyTermDetail ) => {
			setIsOpen( false );
			onSelect( term );
		},
		[ onSelect ]
	);

	// When the input is focused while the panel is closed (a term is already
	// selected), reopen the panel so the user can change their selection.
	const handleInputFocus = useCallback( () => {
		if ( ! isOpen ) {
			setSearch( '' );
			setDebouncedSearch( '' );
			setActiveIndex( -1 );
			setIsOpen( true );
		}
	}, [ isOpen ] );

	const handleKeyDown = useCallback(
		( e: React.KeyboardEvent< HTMLInputElement > ) => {
			switch ( e.key ) {
				case 'Escape':
					e.preventDefault();
					onCancel();
					break;

				case 'ArrowDown':
					e.preventDefault();
					if ( items.length === 0 ) break;
					setActiveIndex( ( prev ) =>
						prev >= items.length - 1 ? 0 : prev + 1
					);
					break;

				case 'ArrowUp':
					e.preventDefault();
					if ( items.length === 0 ) break;
					setActiveIndex( ( prev ) =>
						prev <= 0 ? items.length - 1 : prev - 1
					);
					break;

				case 'Enter':
					e.preventDefault();
					if ( activeIndex >= 0 && activeIndex < items.length ) {
						handleSelect( items[ activeIndex ] );
					}
					break;
			}
		},
		[ items, activeIndex, handleSelect, onCancel ]
	);

	const renderBody = (): JSX.Element => {
		if ( isLoading ) {
			return (
				<div className="iwbe-taxonomy-picker-loading">
					{ __( 'Loading…', 'ihumbak-woo-bulk-edit' ) }
				</div>
			);
		}

		if ( isError ) {
			return (
				<div className="iwbe-taxonomy-picker-error">
					{ __( 'Failed to load terms.', 'ihumbak-woo-bulk-edit' ) }
				</div>
			);
		}

		if ( items.length === 0 ) {
			return (
				<div className="iwbe-taxonomy-picker-empty">
					{ __( 'No terms found.', 'ihumbak-woo-bulk-edit' ) }
				</div>
			);
		}

		return (
			<div
				className="iwbe-taxonomy-picker-dropdown"
				role="listbox"
			>
				{ items.map( ( term, index ) => (
					<div
						key={ term.id }
						className={
							'iwbe-taxonomy-picker-item' +
							( index === activeIndex
								? ' iwbe-taxonomy-picker-item--active'
								: '' )
						}
						role="option"
						aria-selected={ index === activeIndex }
						// Use onMouseDown + preventDefault() so the selection fires
						// before the input's blur event (which would close the picker).
						onMouseDown={ ( e ) => {
							e.preventDefault();
							handleSelect( term );
						} }
					>
						{ term.name }
						{ term.count > 0 && (
							<span className="iwbe-taxonomy-picker-count">
								{ ' ' }({ term.count })
							</span>
						) }
					</div>
				) ) }
			</div>
		);
	};

	return (
		<div className="iwbe-taxonomy-picker" ref={ anchorRef }>
			<input
				ref={ inputRef }
				type="text"
				className={ 'iwbe-taxonomy-picker-input' + ( ! isOpen && selectedLabel ? ' iwbe-taxonomy-picker-input--selected' : '' ) }
				placeholder={ __( 'Search terms…', 'ihumbak-woo-bulk-edit' ) }
				value={ ! isOpen && selectedLabel ? selectedLabel : search }
				onChange={ handleSearchChange }
				onKeyDown={ handleKeyDown }
				onFocus={ handleInputFocus }
				aria-label={ __( 'Search taxonomy terms', 'ihumbak-woo-bulk-edit' ) }
				autoComplete="off"
				readOnly={ ! isOpen && !! selectedLabel }
			/>
			{ /* Panel is rendered in the browser top-layer via the Popover API,
			     escaping overflow:hidden / overflow:auto on all ancestors.
			     Supported: Chrome 114+, Firefox 125+, Safari 17+. */ }
			<div
				ref={ panelRef }
				{ ...{ popover: 'manual' } }
				className="iwbe-taxonomy-picker-panel-wrapper"
			>
				{ renderBody() }
			</div>
		</div>
	);
}
