import { create } from 'zustand';

export interface CellAddress {
	productId: number;
	fieldKey: string;
}

interface EditingState {
	activeCell: CellAddress | null;
	focusedCell: CellAddress | null;
	draftValue: unknown;
	validationError: string | null;

	startEditing: ( productId: number, fieldKey: string ) => void;
	stopEditing: () => void;
	setFocusedCell: ( productId: number, fieldKey: string ) => void;
	clearFocus: () => void;
	setDraftValue: ( value: unknown ) => void;
	setValidationError: ( message: string | null ) => void;
}

function isSameCell(
	a: CellAddress | null,
	productId: number,
	fieldKey: string
): boolean {
	return a !== null && a.productId === productId && a.fieldKey === fieldKey;
}

export const useEditingStore = create< EditingState >()( ( set, get ) => ( {
	activeCell: null,
	focusedCell: null,
	draftValue: undefined,
	validationError: null,

	startEditing: ( productId, fieldKey ) => {
		const { activeCell } = get();
		if ( isSameCell( activeCell, productId, fieldKey ) ) {
			return;
		}
		set( {
			activeCell: { productId, fieldKey },
			focusedCell: { productId, fieldKey },
			draftValue: undefined,
			validationError: null,
		} );
	},

	stopEditing: () => {
		set( {
			activeCell: null,
			draftValue: undefined,
			validationError: null,
		} );
	},

	setFocusedCell: ( productId, fieldKey ) => {
		set( { focusedCell: { productId, fieldKey } } );
	},

	clearFocus: () => {
		set( {
			activeCell: null,
			focusedCell: null,
			draftValue: undefined,
			validationError: null,
		} );
	},

	setDraftValue: ( value ) => {
		set( { draftValue: value } );
	},

	setValidationError: ( message ) => {
		set( { validationError: message } );
	},
} ) );
