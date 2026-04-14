import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { SavedFilterDefinition } from '@/types/api';

export interface RecentFilterEntry {
	id: number | null; // null if ad-hoc (unsaved filter applied manually)
	name: string;
	definition: SavedFilterDefinition;
	usedAt: number; // ms timestamp
}

const MAX_RECENTS = 5;

interface RecentFiltersState {
	recents: RecentFilterEntry[];

	/** Add or update an entry, then trim to MAX_RECENTS. */
	touch: ( entry: RecentFilterEntry ) => void;
	/** Remove the entry with the matching id. No-op if not found. */
	removeById: ( id: number ) => void;
	/** Clear all recent entries. */
	clear: () => void;
}

export const useRecentFiltersStore = create< RecentFiltersState >()(
	persist(
		( set ) => ( {
			recents: [],

			touch: ( entry ) =>
				set( ( state ) => {
					// Remove existing entry with same id (if any)
					const filtered =
						entry.id !== null
							? state.recents.filter( ( r ) => r.id !== entry.id )
							: state.recents;

					// Prepend + trim to MAX_RECENTS
					const next = [ entry, ...filtered ].slice( 0, MAX_RECENTS );
					return { recents: next };
				} ),

			removeById: ( id ) =>
				set( ( state ) => ( {
					recents: state.recents.filter( ( r ) => r.id !== id ),
				} ) ),

			clear: () => set( { recents: [] } ),
		} ),
		{
			name: 'iwbe-recent-filters-v1',
		}
	)
);
