import { useState, useCallback, useRef, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useTaxonomyTerms } from '@/hooks/useTaxonomyTerms';
import type { TaxonomyTermDetail } from '@/types/api';

interface TaxonomyTermPickerProps {
	fieldKey: string;
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
 */
export function TaxonomyTermPicker( {
	fieldKey,
	onSelect,
	onCancel,
}: TaxonomyTermPickerProps ): JSX.Element {
	const [ search, setSearch ] = useState( '' );
	const [ debouncedSearch, setDebouncedSearch ] = useState( '' );
	const [ activeIndex, setActiveIndex ] = useState( -1 );

	const inputRef = useRef< HTMLInputElement >( null );
	const debounceRef = useRef< ReturnType< typeof setTimeout > | null >( null );

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
						onSelect( items[ activeIndex ] );
					}
					break;
			}
		},
		[ items, activeIndex, onSelect, onCancel ]
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
							onSelect( term );
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
		<div className="iwbe-taxonomy-picker">
			<input
				ref={ inputRef }
				type="text"
				className="iwbe-taxonomy-picker-input"
				placeholder={ __( 'Search terms…', 'ihumbak-woo-bulk-edit' ) }
				value={ search }
				onChange={ handleSearchChange }
				onKeyDown={ handleKeyDown }
				aria-label={ __( 'Search taxonomy terms', 'ihumbak-woo-bulk-edit' ) }
				autoComplete="off"
			/>
			{ renderBody() }
		</div>
	);
}
