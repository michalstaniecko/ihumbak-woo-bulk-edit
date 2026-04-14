import { useState, useRef, useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import type { Field } from '@/types/api';
import { PINNED_COLUMN_IDS } from '@/store/useColumnVisibilityStore';
import { getColumnGroups } from './groups';

export interface ColumnVisibilityDialogProps {
	fields: Field[];
	hidden: Set< string >;
	onToggle: ( columnId: string ) => void;
	onSelectAll: () => void;
	onDeselectAll: () => void;
	onResetToDefault: () => void;
}

/**
 * Popover dialog for managing column visibility.
 *
 * - Grouped checkboxes with search filtering
 * - Counter showing visible / total columns
 * - Footer with Select All, Deselect All, Reset to Default
 */
export function ColumnVisibilityDialog( {
	fields,
	hidden,
	onToggle,
	onSelectAll,
	onDeselectAll,
	onResetToDefault,
}: ColumnVisibilityDialogProps ): JSX.Element {
	const [ search, setSearch ] = useState( '' );
	const searchRef = useRef< HTMLInputElement >( null );

	// Focus search input on mount
	useEffect( () => {
		searchRef.current?.focus();
	}, [] );

	// Exclude pinned columns — they are never shown in the dialog
	const configurableFields = fields.filter(
		( f ) => ! PINNED_COLUMN_IDS.has( f.key )
	);

	const normalizedSearch = search.trim().toLowerCase();

	const filteredFields =
		normalizedSearch === ''
			? configurableFields
			: configurableFields.filter( ( f ) =>
					f.label.toLowerCase().includes( normalizedSearch )
			  );

	const visibleCount = configurableFields.filter(
		( f ) => ! hidden.has( f.key )
	).length;
	const totalCount = configurableFields.length;

	// Build grouped layout (only groups that have visible fields after filter)
	const groups = getColumnGroups();
	const groupedFields: Array< { label: string; fields: Field[] } > = [];

	for ( const group of groups ) {
		const groupFields = filteredFields.filter( ( f ) =>
			group.keys.includes( f.key )
		);
		if ( groupFields.length > 0 ) {
			groupedFields.push( { label: group.label, fields: groupFields } );
		}
	}

	// Fields not in any group go to "Other"
	const allGroupedKeys = new Set( groups.flatMap( ( g ) => g.keys ) );
	const ungroupedFields = filteredFields.filter(
		( f ) => ! allGroupedKeys.has( f.key )
	);
	if ( ungroupedFields.length > 0 ) {
		const otherGroup = groupedFields.find(
			( g ) => g.label === __( 'Other', 'ihumbak-woo-bulk-edit' )
		);
		if ( otherGroup ) {
			otherGroup.fields = [ ...otherGroup.fields, ...ungroupedFields ];
		} else {
			groupedFields.push( {
				label: __( 'Other', 'ihumbak-woo-bulk-edit' ),
				fields: ungroupedFields,
			} );
		}
	}

	const handleSearchInput = useCallback(
		( e: React.FormEvent< HTMLInputElement > ) => {
			setSearch( ( e.target as HTMLInputElement ).value );
		},
		[]
	);

	return (
		<div
			className="iwbe-column-visibility-dialog"
			role="dialog"
			aria-label={ __( 'Column visibility', 'ihumbak-woo-bulk-edit' ) }
		>
			<div className="iwbe-column-visibility-counter">
				{ visibleCount }
				{ ' ' }
				{ __( 'of', 'ihumbak-woo-bulk-edit' ) }
				{ ' ' }
				{ totalCount }
				{ ' ' }
				{ __( 'visible', 'ihumbak-woo-bulk-edit' ) }
			</div>

			<input
				ref={ searchRef }
				type="text"
				className="iwbe-column-visibility-search"
				placeholder={ __( 'Search columns…', 'ihumbak-woo-bulk-edit' ) }
				value={ search }
				onInput={ handleSearchInput }
				onChange={ ( e ) => setSearch( e.target.value ) }
			/>

			<div className="iwbe-column-visibility-groups">
				{ groupedFields.length === 0 && (
					<p className="iwbe-column-visibility-empty">
						{ __( 'No columns match your search.', 'ihumbak-woo-bulk-edit' ) }
					</p>
				) }

				{ groupedFields.map( ( group ) => (
					<div key={ group.label } className="iwbe-column-visibility-group">
						<div className="iwbe-column-visibility-group-label">
							{ group.label }
						</div>
						{ group.fields.map( ( field ) => {
							const isVisible = ! hidden.has( field.key );
							return (
								<label
									key={ field.key }
									className="iwbe-column-visibility-item"
								>
									<input
										type="checkbox"
										data-column-id={ field.key }
										checked={ isVisible }
										onChange={ () => onToggle( field.key ) }
									/>
									{ field.label }
								</label>
							);
						} ) }
					</div>
				) ) }
			</div>

			<div className="iwbe-column-visibility-footer">
				<button
					type="button"
					className="button button-small"
					onClick={ onSelectAll }
				>
					{ __( 'Select all', 'ihumbak-woo-bulk-edit' ) }
				</button>
				<button
					type="button"
					className="button button-small"
					onClick={ () => onDeselectAll() }
				>
					{ __( 'Deselect all', 'ihumbak-woo-bulk-edit' ) }
				</button>
				<button
					type="button"
					className="button button-small"
					onClick={ onResetToDefault }
				>
					{ __( 'Reset to default', 'ihumbak-woo-bulk-edit' ) }
				</button>
			</div>
		</div>
	);
}
