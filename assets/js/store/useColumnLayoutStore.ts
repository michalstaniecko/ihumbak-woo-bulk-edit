import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import type { ColumnSizingState, ColumnOrderState } from '@tanstack/react-table';

const PINNED_COLUMN_IDS = [ 'select', 'id' ];
const MIN_COLUMN_WIDTH = 50;

interface ColumnLayoutState {
	/** Map of columnId -> width in pixels. Missing keys fall back to columnDef.size. */
	columnSizing: ColumnSizingState;
	/** Ordered array of column IDs. Empty means "use default order from columnDefs". */
	columnOrder: ColumnOrderState;

	setColumnWidth: ( columnId: string, width: number ) => void;
	setColumnSizing: ( sizing: ColumnSizingState ) => void;
	setColumnOrder: ( order: ColumnOrderState ) => void;
	resetLayout: () => void;
}

interface PersistedSlice {
	columnSizing: ColumnSizingState;
	columnOrder: ColumnOrderState;
}

export const useColumnLayoutStore = create< ColumnLayoutState >()(
	persist(
		( set, get ) => ( {
			columnSizing: {},
			columnOrder: [],

			setColumnWidth: ( columnId, width ) => {
				const clamped = Math.max( MIN_COLUMN_WIDTH, width );
				set( { columnSizing: { ...get().columnSizing, [ columnId ]: clamped } } );
			},

			setColumnSizing: ( sizing ) => {
				const clamped: ColumnSizingState = {};
				for ( const [ id, width ] of Object.entries( sizing ) ) {
					clamped[ id ] = Math.max( MIN_COLUMN_WIDTH, width );
				}
				set( { columnSizing: clamped } );
			},

			setColumnOrder: ( order ) => {
				if ( order.length === 0 ) {
					set( { columnOrder: [] } );
					return;
				}
				// Ensure pinned columns stay at the beginning
				const pinned = PINNED_COLUMN_IDS.filter( ( id ) => order.includes( id ) );
				const rest = order.filter( ( id ) => ! PINNED_COLUMN_IDS.includes( id ) );
				set( { columnOrder: [ ...pinned, ...rest ] } );
			},

			resetLayout: () => {
				set( { columnSizing: {}, columnOrder: [] } );
			},
		} ),
		{
			name: 'iwbe_column_layout',
			storage: createJSONStorage( () => localStorage ),
			partialize: ( state ): PersistedSlice => ( {
				columnSizing: state.columnSizing,
				columnOrder: state.columnOrder,
			} ),
		}
	)
);
