import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock persist middleware to avoid localStorage in tests
vi.mock( 'zustand/middleware', async ( importOriginal ) => {
	const actual = await importOriginal< typeof import( 'zustand/middleware' ) >();
	return {
		...actual,
		persist: ( config: unknown ) => config,
	};
} );

// Import AFTER mocking persist
const {
	useColumnVisibilityStore,
	PINNED_COLUMN_IDS,
	DEFAULT_VISIBLE_COLUMNS,
} = await import( '../useColumnVisibilityStore' );

function getState() {
	return useColumnVisibilityStore.getState();
}

function reset() {
	useColumnVisibilityStore.setState( {
		hidden: new Set< string >(),
		isHydrated: false,
		isLoading: false,
		error: null,
	} );
}

const ALL_COLUMN_IDS = [
	'select',
	'id',
	'name',
	'sku',
	'regular_price',
	'sale_price',
	'stock_quantity',
	'status',
	'categories',
	'description',
	'weight',
	'length',
];

describe( 'useColumnVisibilityStore', () => {
	beforeEach( () => {
		reset();
	} );

	describe( 'PINNED_COLUMN_IDS', () => {
		it( 'contains select and id', () => {
			expect( PINNED_COLUMN_IDS.has( 'select' ) ).toBe( true );
			expect( PINNED_COLUMN_IDS.has( 'id' ) ).toBe( true );
		} );

		it( 'does not contain regular field columns', () => {
			expect( PINNED_COLUMN_IDS.has( 'name' ) ).toBe( false );
			expect( PINNED_COLUMN_IDS.has( 'description' ) ).toBe( false );
		} );
	} );

	describe( 'DEFAULT_VISIBLE_COLUMNS', () => {
		it( 'includes name, sku, regular_price, sale_price, stock_quantity, status, categories', () => {
			expect( DEFAULT_VISIBLE_COLUMNS ).toContain( 'name' );
			expect( DEFAULT_VISIBLE_COLUMNS ).toContain( 'sku' );
			expect( DEFAULT_VISIBLE_COLUMNS ).toContain( 'regular_price' );
			expect( DEFAULT_VISIBLE_COLUMNS ).toContain( 'sale_price' );
			expect( DEFAULT_VISIBLE_COLUMNS ).toContain( 'stock_quantity' );
			expect( DEFAULT_VISIBLE_COLUMNS ).toContain( 'status' );
			expect( DEFAULT_VISIBLE_COLUMNS ).toContain( 'categories' );
		} );
	} );

	describe( 'setHidden', () => {
		it( 'sets the hidden set from an array', () => {
			getState().setHidden( [ 'description', 'weight' ] );
			const { hidden } = getState();
			expect( hidden.has( 'description' ) ).toBe( true );
			expect( hidden.has( 'weight' ) ).toBe( true );
		} );

		it( 'filters out pinned columns', () => {
			getState().setHidden( [ 'select', 'id', 'description' ] );
			const { hidden } = getState();
			expect( hidden.has( 'select' ) ).toBe( false );
			expect( hidden.has( 'id' ) ).toBe( false );
			expect( hidden.has( 'description' ) ).toBe( true );
		} );

		it( 'replaces existing hidden set', () => {
			getState().setHidden( [ 'description', 'weight' ] );
			getState().setHidden( [ 'length' ] );
			const { hidden } = getState();
			expect( hidden.has( 'description' ) ).toBe( false );
			expect( hidden.has( 'weight' ) ).toBe( false );
			expect( hidden.has( 'length' ) ).toBe( true );
		} );
	} );

	describe( 'toggle', () => {
		it( 'adds a column to hidden if not already hidden', () => {
			getState().toggle( 'description' );
			expect( getState().hidden.has( 'description' ) ).toBe( true );
		} );

		it( 'removes a column from hidden if already hidden', () => {
			getState().setHidden( [ 'description' ] );
			getState().toggle( 'description' );
			expect( getState().hidden.has( 'description' ) ).toBe( false );
		} );

		it( 'is a no-op for pinned column "select"', () => {
			getState().toggle( 'select' );
			expect( getState().hidden.has( 'select' ) ).toBe( false );
		} );

		it( 'is a no-op for pinned column "id"', () => {
			getState().toggle( 'id' );
			expect( getState().hidden.has( 'id' ) ).toBe( false );
		} );
	} );

	describe( 'selectAll', () => {
		it( 'clears all hidden columns', () => {
			getState().setHidden( [ 'description', 'weight', 'length' ] );
			getState().selectAll();
			expect( getState().hidden.size ).toBe( 0 );
		} );
	} );

	describe( 'deselectAll', () => {
		it( 'hides all columns except pinned ones', () => {
			getState().deselectAll( ALL_COLUMN_IDS );
			const { hidden } = getState();
			// Pinned columns must never be in hidden
			expect( hidden.has( 'select' ) ).toBe( false );
			expect( hidden.has( 'id' ) ).toBe( false );
			// Non-pinned columns should all be hidden
			expect( hidden.has( 'name' ) ).toBe( true );
			expect( hidden.has( 'description' ) ).toBe( true );
		} );
	} );

	describe( 'resetToDefault', () => {
		it( 'hides all columns not in DEFAULT_VISIBLE_COLUMNS and not pinned', () => {
			getState().resetToDefault( ALL_COLUMN_IDS );
			const { hidden } = getState();

			// Pinned never hidden
			expect( hidden.has( 'select' ) ).toBe( false );
			expect( hidden.has( 'id' ) ).toBe( false );

			// Default-visible columns should NOT be hidden
			for ( const col of DEFAULT_VISIBLE_COLUMNS ) {
				expect( hidden.has( col ), `Expected ${ col } to be visible` ).toBe(
					false
				);
			}

			// Non-default, non-pinned columns should be hidden
			expect( hidden.has( 'description' ) ).toBe( true );
			expect( hidden.has( 'weight' ) ).toBe( true );
			expect( hidden.has( 'length' ) ).toBe( true );
		} );

		it( 'keeps pinned columns visible even if they were previously hidden', () => {
			// Manually force pinned into hidden to test guard
			useColumnVisibilityStore.setState( {
				hidden: new Set( [ 'select', 'id', 'name' ] ),
			} );
			getState().resetToDefault( ALL_COLUMN_IDS );
			const { hidden } = getState();
			expect( hidden.has( 'select' ) ).toBe( false );
			expect( hidden.has( 'id' ) ).toBe( false );
		} );
	} );

	describe( 'setHydrated', () => {
		it( 'sets isHydrated to true', () => {
			expect( getState().isHydrated ).toBe( false );
			getState().setHydrated( true );
			expect( getState().isHydrated ).toBe( true );
		} );
	} );

	describe( 'setLoading', () => {
		it( 'updates isLoading flag', () => {
			getState().setLoading( true );
			expect( getState().isLoading ).toBe( true );
			getState().setLoading( false );
			expect( getState().isLoading ).toBe( false );
		} );
	} );

	describe( 'setError', () => {
		it( 'stores an error string', () => {
			getState().setError( 'Something went wrong' );
			expect( getState().error ).toBe( 'Something went wrong' );
		} );

		it( 'can clear the error by passing null', () => {
			getState().setError( 'err' );
			getState().setError( null );
			expect( getState().error ).toBeNull();
		} );
	} );

	describe( 'clear', () => {
		it( 'resets the hidden set to empty', () => {
			getState().setHidden( [ 'description', 'weight' ] );
			getState().clear();
			expect( getState().hidden.size ).toBe( 0 );
		} );
	} );
} );
