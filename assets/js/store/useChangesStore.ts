import { create } from 'zustand';

const MAX_HISTORY = 50;

export interface CellChange {
	oldValue: unknown;
	newValue: unknown;
}

export type ChangeMap = Record< string, Record< string, CellChange > >;

export interface HistoryEntry {
	productId: string;
	field: string;
	oldValue: unknown;
	newValue: unknown;
}

interface ChangesState {
	changes: ChangeMap;
	past: HistoryEntry[];
	future: HistoryEntry[];

	setChange: (
		productId: number,
		field: string,
		oldValue: unknown,
		newValue: unknown
	) => void;
	undo: () => void;
	redo: () => void;
	discardAll: () => void;

	getChangedValue: (
		productId: number,
		field: string
	) => unknown | undefined;
	hasChanges: () => boolean;
	changedCellsCount: () => number;
	changedProductsCount: () => number;
	canUndo: () => boolean;
	canRedo: () => boolean;
}

function applyChange(
	changes: ChangeMap,
	productId: string,
	field: string,
	oldValue: unknown,
	newValue: unknown
): ChangeMap {
	const updated = { ...changes };

	const originalOldValue =
		updated[ productId ]?.[ field ]?.oldValue ?? oldValue;

	if ( newValue === originalOldValue ) {
		// Change reverted to original — remove entry
		if ( updated[ productId ] ) {
			const { [ field ]: _, ...rest } = updated[ productId ];
			if ( Object.keys( rest ).length === 0 ) {
				const { [ productId ]: __, ...withoutProduct } = updated;
				return withoutProduct;
			}
			updated[ productId ] = rest;
		}
		return updated;
	}

	updated[ productId ] = {
		...( updated[ productId ] ?? {} ),
		[ field ]: { oldValue: originalOldValue, newValue },
	};

	return updated;
}

function revertChange(
	changes: ChangeMap,
	productId: string,
	field: string,
	oldValue: unknown
): ChangeMap {
	const updated = { ...changes };
	const currentEntry = updated[ productId ]?.[ field ];

	if ( ! currentEntry ) {
		// No current change — restoring means setting the old value back
		// This happens when undoing the very first change on a cell
		return updated;
	}

	if ( oldValue === currentEntry.oldValue ) {
		// Reverting back to original — remove entry
		const { [ field ]: _, ...rest } = updated[ productId ];
		if ( Object.keys( rest ).length === 0 ) {
			const { [ productId ]: __, ...withoutProduct } = updated;
			return withoutProduct;
		}
		updated[ productId ] = rest;
		return updated;
	}

	// Partial revert — restore to an intermediate value
	updated[ productId ] = {
		...updated[ productId ],
		[ field ]: { oldValue: currentEntry.oldValue, newValue: oldValue },
	};

	return updated;
}

export const useChangesStore = create< ChangesState >()( ( set, get ) => ( {
	changes: {},
	past: [],
	future: [],

	setChange: ( productId, field, oldValue, newValue ) => {
		const id = String( productId );
		const entry: HistoryEntry = {
			productId: id,
			field,
			oldValue,
			newValue,
		};

		set( ( state ) => ( {
			changes: applyChange(
				state.changes,
				id,
				field,
				oldValue,
				newValue
			),
			past:
				state.past.length >= MAX_HISTORY
					? [ ...state.past.slice( 1 ), entry ]
					: [ ...state.past, entry ],
			future: [],
		} ) );
	},

	undo: () => {
		const { past, future, changes } = get();
		if ( past.length === 0 ) {
			return;
		}

		const entry = past[ past.length - 1 ];
		const newPast = past.slice( 0, -1 );

		set( {
			changes: revertChange(
				changes,
				entry.productId,
				entry.field,
				entry.oldValue
			),
			past: newPast,
			future: [ ...future, entry ],
		} );
	},

	redo: () => {
		const { past, future, changes } = get();
		if ( future.length === 0 ) {
			return;
		}

		const entry = future[ future.length - 1 ];
		const newFuture = future.slice( 0, -1 );

		set( {
			changes: applyChange(
				changes,
				entry.productId,
				entry.field,
				entry.oldValue,
				entry.newValue
			),
			past: [ ...past, entry ],
			future: newFuture,
		} );
	},

	discardAll: () => {
		set( {
			changes: {},
			past: [],
			future: [],
		} );
	},

	getChangedValue: ( productId, field ) => {
		const entry = get().changes[ String( productId ) ]?.[ field ];
		return entry?.newValue;
	},

	hasChanges: () => {
		return Object.keys( get().changes ).length > 0;
	},

	changedCellsCount: () => {
		const { changes } = get();
		let count = 0;
		for ( const productId of Object.keys( changes ) ) {
			count += Object.keys( changes[ productId ] ).length;
		}
		return count;
	},

	changedProductsCount: () => {
		return Object.keys( get().changes ).length;
	},

	canUndo: () => {
		return get().past.length > 0;
	},

	canRedo: () => {
		return get().future.length > 0;
	},
} ) );
