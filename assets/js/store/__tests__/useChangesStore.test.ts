import { describe, it, expect, beforeEach } from 'vitest';
import { useChangesStore } from '../useChangesStore';

function getState() {
	return useChangesStore.getState();
}

function reset() {
	useChangesStore.setState( {
		changes: {},
		past: [],
		future: [],
	} );
}

describe( 'useChangesStore', () => {
	beforeEach( () => {
		reset();
	} );

	describe( 'setChange', () => {
		it( 'should record a cell change', () => {
			getState().setChange( 1, 'name', 'Old Name', 'New Name' );

			expect( getState().changes ).toEqual( {
				'1': {
					name: { oldValue: 'Old Name', newValue: 'New Name' },
				},
			} );
		} );

		it( 'should record multiple changes for the same product', () => {
			getState().setChange( 1, 'name', 'Old Name', 'New Name' );
			getState().setChange( 1, 'sku', 'ABC', 'XYZ' );

			expect( getState().changes ).toEqual( {
				'1': {
					name: { oldValue: 'Old Name', newValue: 'New Name' },
					sku: { oldValue: 'ABC', newValue: 'XYZ' },
				},
			} );
		} );

		it( 'should record changes for different products', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 2, 'name', 'C', 'D' );

			expect( Object.keys( getState().changes ) ).toEqual( [
				'1',
				'2',
			] );
		} );

		it( 'should remove entry when newValue equals original oldValue', () => {
			getState().setChange( 1, 'name', 'Original', 'Changed' );
			getState().setChange( 1, 'name', 'Changed', 'Original' );

			expect( getState().changes ).toEqual( {} );
		} );

		it( 'should remove product key when last field change is reverted', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 1, 'name', 'B', 'A' );

			expect( getState().changes ).toEqual( {} );
		} );

		it( 'should update existing change preserving original oldValue', () => {
			getState().setChange( 1, 'name', 'Original', 'First' );
			getState().setChange( 1, 'name', 'First', 'Second' );

			expect( getState().changes[ '1' ].name ).toEqual( {
				oldValue: 'Original',
				newValue: 'Second',
			} );
		} );

		it( 'should push entry to past history', () => {
			getState().setChange( 1, 'name', 'A', 'B' );

			expect( getState().past ).toHaveLength( 1 );
			expect( getState().past[ 0 ] ).toEqual( {
				productId: '1',
				field: 'name',
				oldValue: 'A',
				newValue: 'B',
			} );
		} );

		it( 'should clear future on new change', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().undo();
			expect( getState().future ).toHaveLength( 1 );

			getState().setChange( 1, 'name', 'A', 'C' );
			expect( getState().future ).toHaveLength( 0 );
		} );

		it( 'should cap history at 50 entries', () => {
			for ( let i = 0; i < 55; i++ ) {
				getState().setChange( i, 'name', `old${ i }`, `new${ i }` );
			}

			expect( getState().past ).toHaveLength( 50 );
			// Oldest entries should have been dropped
			expect( getState().past[ 0 ].productId ).toBe( '5' );
		} );
	} );

	describe( 'undo', () => {
		it( 'should revert the last change', () => {
			getState().setChange( 1, 'name', 'Original', 'Changed' );
			getState().undo();

			expect( getState().changes ).toEqual( {} );
		} );

		it( 'should move entry to future stack', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().undo();

			expect( getState().past ).toHaveLength( 0 );
			expect( getState().future ).toHaveLength( 1 );
			expect( getState().future[ 0 ] ).toEqual( {
				productId: '1',
				field: 'name',
				oldValue: 'A',
				newValue: 'B',
			} );
		} );

		it( 'should do nothing when past is empty', () => {
			getState().undo();

			expect( getState().changes ).toEqual( {} );
			expect( getState().past ).toHaveLength( 0 );
			expect( getState().future ).toHaveLength( 0 );
		} );

		it( 'should handle multiple undos in sequence', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 2, 'sku', 'X', 'Y' );

			getState().undo();
			expect( getState().changes ).toEqual( {
				'1': {
					name: { oldValue: 'A', newValue: 'B' },
				},
			} );

			getState().undo();
			expect( getState().changes ).toEqual( {} );
		} );

		it( 'should revert to intermediate value for multiple edits on same cell', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 1, 'name', 'B', 'C' );

			getState().undo();
			// After undoing "B→C", the change map should show A→B
			expect( getState().changes[ '1' ].name ).toEqual( {
				oldValue: 'A',
				newValue: 'B',
			} );
		} );
	} );

	describe( 'redo', () => {
		it( 'should re-apply an undone change', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().undo();
			getState().redo();

			expect( getState().changes ).toEqual( {
				'1': {
					name: { oldValue: 'A', newValue: 'B' },
				},
			} );
		} );

		it( 'should move entry back to past stack', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().undo();
			getState().redo();

			expect( getState().past ).toHaveLength( 1 );
			expect( getState().future ).toHaveLength( 0 );
		} );

		it( 'should do nothing when future is empty', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().redo();

			// No change — still has the original change
			expect( getState().changes ).toEqual( {
				'1': {
					name: { oldValue: 'A', newValue: 'B' },
				},
			} );
			expect( getState().future ).toHaveLength( 0 );
		} );

		it( 'should handle undo then redo cycle', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 2, 'sku', 'X', 'Y' );

			getState().undo();
			getState().undo();
			getState().redo();

			expect( getState().changes ).toEqual( {
				'1': {
					name: { oldValue: 'A', newValue: 'B' },
				},
			} );
		} );
	} );

	describe( 'discardAll', () => {
		it( 'should clear all changes and history', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 2, 'sku', 'X', 'Y' );
			getState().undo();

			getState().discardAll();

			expect( getState().changes ).toEqual( {} );
			expect( getState().past ).toHaveLength( 0 );
			expect( getState().future ).toHaveLength( 0 );
		} );
	} );

	describe( 'selectors', () => {
		it( 'getChangedValue returns newValue for existing change', () => {
			getState().setChange( 1, 'name', 'A', 'B' );

			expect( getState().getChangedValue( 1, 'name' ) ).toBe( 'B' );
		} );

		it( 'getChangedValue returns undefined for non-existing change', () => {
			expect(
				getState().getChangedValue( 999, 'name' )
			).toBeUndefined();
		} );

		it( 'hasChanges returns false when empty', () => {
			expect( getState().hasChanges() ).toBe( false );
		} );

		it( 'hasChanges returns true when changes exist', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			expect( getState().hasChanges() ).toBe( true );
		} );

		it( 'changedCellsCount returns total changed cells', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 1, 'sku', 'X', 'Y' );
			getState().setChange( 2, 'name', 'C', 'D' );

			expect( getState().changedCellsCount() ).toBe( 3 );
		} );

		it( 'changedProductsCount returns number of affected products', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 1, 'sku', 'X', 'Y' );
			getState().setChange( 2, 'name', 'C', 'D' );

			expect( getState().changedProductsCount() ).toBe( 2 );
		} );

		it( 'canUndo returns true when past has entries', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			expect( getState().canUndo() ).toBe( true );
		} );

		it( 'canUndo returns false when past is empty', () => {
			expect( getState().canUndo() ).toBe( false );
		} );

		it( 'canRedo returns true when future has entries', () => {
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().undo();
			expect( getState().canRedo() ).toBe( true );
		} );

		it( 'canRedo returns false when future is empty', () => {
			expect( getState().canRedo() ).toBe( false );
		} );
	} );

	describe( 'pagination persistence', () => {
		it( 'should retain changes across simulated page switches', () => {
			// Simulate: user edits on page 1
			getState().setChange( 1, 'name', 'A', 'B' );
			getState().setChange( 2, 'sku', 'X', 'Y' );

			// Simulate: page change (store is global, unaffected)
			// User edits on page 2
			getState().setChange( 51, 'name', 'C', 'D' );

			// All changes from both "pages" are preserved
			expect( getState().changedProductsCount() ).toBe( 3 );
			expect( getState().changedCellsCount() ).toBe( 3 );
			expect( getState().getChangedValue( 1, 'name' ) ).toBe( 'B' );
			expect( getState().getChangedValue( 51, 'name' ) ).toBe( 'D' );
		} );
	} );
} );
