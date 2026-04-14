import { create } from 'zustand';

interface ExpansionState {
	expanded: Set< number >;

	isExpanded: ( parentId: number ) => boolean;
	toggle: ( parentId: number ) => void;
	expand: ( parentId: number ) => void;
	collapse: ( parentId: number ) => void;
	clear: () => void;
}

export const useExpansionStore = create< ExpansionState >()( ( set, get ) => ( {
	expanded: new Set< number >(),

	isExpanded: ( parentId ) => {
		return get().expanded.has( parentId );
	},

	toggle: ( parentId ) => {
		set( ( state ) => {
			const next = new Set( state.expanded );
			if ( next.has( parentId ) ) {
				next.delete( parentId );
			} else {
				next.add( parentId );
			}
			return { expanded: next };
		} );
	},

	expand: ( parentId ) => {
		set( ( state ) => {
			if ( state.expanded.has( parentId ) ) {
				return state;
			}
			const next = new Set( state.expanded );
			next.add( parentId );
			return { expanded: next };
		} );
	},

	collapse: ( parentId ) => {
		set( ( state ) => {
			if ( ! state.expanded.has( parentId ) ) {
				return state;
			}
			const next = new Set( state.expanded );
			next.delete( parentId );
			return { expanded: next };
		} );
	},

	clear: () => {
		set( { expanded: new Set< number >() } );
	},
} ) );
