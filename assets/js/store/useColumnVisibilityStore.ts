import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';

/**
 * Column IDs that are always visible and cannot be toggled.
 */
export const PINNED_COLUMN_IDS = new Set< string >( [ 'select', 'id' ] );

/**
 * Default set of visible column IDs (everything NOT in this list will be hidden
 * when `resetToDefault` is called).
 */
export const DEFAULT_VISIBLE_COLUMNS: ReadonlyArray< string > = [
	'name',
	'sku',
	'regular_price',
	'sale_price',
	'stock_quantity',
	'status',
	'categories',
];

interface ColumnVisibilityState {
	/** Set of column IDs that are currently hidden. */
	hidden: Set< string >;
	/** True once server preferences have been applied. */
	isHydrated: boolean;
	isLoading: boolean;
	error: string | null;

	/** Replace the hidden set (filters pinned columns automatically). */
	setHidden: ( columnIds: string[] ) => void;
	/** Toggle visibility of a single column (no-op for pinned). */
	toggle: ( columnId: string ) => void;
	/** Make all columns visible (clear hidden set). */
	selectAll: () => void;
	/** Hide all non-pinned columns. */
	deselectAll: ( allColumnIds: string[] ) => void;
	/** Hide columns not in DEFAULT_VISIBLE_COLUMNS (pinned always visible). */
	resetToDefault: ( allColumnIds: string[] ) => void;
	/** Clear the hidden set. */
	clear: () => void;
	setHydrated: ( value: boolean ) => void;
	setLoading: ( value: boolean ) => void;
	setError: ( error: string | null ) => void;
}

/**
 * Persisted slice shape (array-based so JSON serialization works).
 * The Set<string> is converted to/from string[] during serialization.
 */
interface PersistedSlice {
	hidden: string[];
}

export const useColumnVisibilityStore = create< ColumnVisibilityState >()(
	persist(
		( set, get ) => ( {
			hidden: new Set< string >(),
			isHydrated: false,
			isLoading: false,
			error: null,

			setHidden: ( columnIds ) => {
				const filtered = new Set(
					columnIds.filter( ( id ) => ! PINNED_COLUMN_IDS.has( id ) )
				);
				set( { hidden: filtered } );
			},

			toggle: ( columnId ) => {
				if ( PINNED_COLUMN_IDS.has( columnId ) ) return;
				const hidden = new Set( get().hidden );
				if ( hidden.has( columnId ) ) {
					hidden.delete( columnId );
				} else {
					hidden.add( columnId );
				}
				set( { hidden } );
			},

			selectAll: () => {
				set( { hidden: new Set< string >() } );
			},

			deselectAll: ( allColumnIds ) => {
				const hidden = new Set(
					allColumnIds.filter( ( id ) => ! PINNED_COLUMN_IDS.has( id ) )
				);
				set( { hidden } );
			},

			resetToDefault: ( allColumnIds ) => {
				const defaultVisible = new Set( DEFAULT_VISIBLE_COLUMNS );
				const hidden = new Set(
					allColumnIds.filter(
						( id ) =>
							! PINNED_COLUMN_IDS.has( id ) && ! defaultVisible.has( id )
					)
				);
				set( { hidden } );
			},

			clear: () => {
				set( { hidden: new Set< string >() } );
			},

			setHydrated: ( value ) => set( { isHydrated: value } ),
			setLoading: ( value ) => set( { isLoading: value } ),
			setError: ( error ) => set( { error } ),
		} ),
		{
			name: 'iwbe_column_visibility',
			storage: createJSONStorage< PersistedSlice >( () => ( {
				getItem: ( key: string ) => {
					try {
						const item = localStorage.getItem( key );
						if ( ! item ) return null;
						const raw = JSON.parse( item ) as {
							state: { hidden: string[] };
							version?: number;
						};
						// Re-serialize with the hidden array (stays as array in JSON)
						return JSON.stringify( {
							state: { hidden: raw.state?.hidden ?? [] },
							version: raw.version,
						} );
					} catch {
						return null;
					}
				},
				setItem: ( key: string, value: string ) => {
					localStorage.setItem( key, value );
				},
				removeItem: ( key: string ) => {
					localStorage.removeItem( key );
				},
			} ) ),
			partialize: ( state ): PersistedSlice => ( {
				hidden: [ ...state.hidden ],
			} ),
			merge: ( persisted, current ) => {
				const p = persisted as PersistedSlice;
				return {
					...current,
					hidden: new Set( p.hidden ?? [] ),
				};
			},
		}
	)
);
