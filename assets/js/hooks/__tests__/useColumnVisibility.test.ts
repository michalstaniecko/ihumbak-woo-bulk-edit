import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mocks — set up before any imports that resolve these modules
// ---------------------------------------------------------------------------

// Mock persist middleware to avoid localStorage in tests
vi.mock( 'zustand/middleware', async ( importOriginal ) => {
	const actual = await importOriginal< typeof import( 'zustand/middleware' ) >();
	return {
		...actual,
		persist: ( config: unknown ) => config,
	};
} );

const mockFetchColumnVisibility = vi.fn();
const mockUpdateColumnVisibility = vi.fn();
const mockResetColumnVisibility = vi.fn();

vi.mock( '@/api/userPreferences', () => ( {
	fetchColumnVisibility: ( ...args: unknown[] ) =>
		mockFetchColumnVisibility( ...args ),
	updateColumnVisibility: ( ...args: unknown[] ) =>
		mockUpdateColumnVisibility( ...args ),
	resetColumnVisibility: ( ...args: unknown[] ) =>
		mockResetColumnVisibility( ...args ),
} ) );

// ---------------------------------------------------------------------------
// Test imports (after mocks)
// ---------------------------------------------------------------------------

const { useColumnVisibilityStore } = await import(
	'@/store/useColumnVisibilityStore'
);
const { fetchColumnVisibility, updateColumnVisibility, resetColumnVisibility } =
	await import( '@/api/userPreferences' );

function resetStore() {
	useColumnVisibilityStore.setState( {
		hidden: new Set< string >(),
		isHydrated: false,
		isLoading: false,
		error: null,
	} );
}

describe( 'userPreferences API module', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		resetStore();
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'fetchColumnVisibility is exported as a function', () => {
		expect( typeof fetchColumnVisibility ).toBe( 'function' );
	} );

	it( 'updateColumnVisibility is exported as a function', () => {
		expect( typeof updateColumnVisibility ).toBe( 'function' );
	} );

	it( 'resetColumnVisibility is exported as a function', () => {
		expect( typeof resetColumnVisibility ).toBe( 'function' );
	} );
} );

describe( 'useColumnVisibilityStore hydration', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		resetStore();
	} );

	it( 'starts with isHydrated = false', () => {
		expect( useColumnVisibilityStore.getState().isHydrated ).toBe( false );
	} );

	it( 'setHydrated transitions to true', () => {
		useColumnVisibilityStore.getState().setHydrated( true );
		expect( useColumnVisibilityStore.getState().isHydrated ).toBe( true );
	} );

	it( 'setHidden hydrates store content', () => {
		useColumnVisibilityStore.getState().setHidden( [ 'description', 'weight' ] );
		const { hidden } = useColumnVisibilityStore.getState();
		expect( hidden.has( 'description' ) ).toBe( true );
		expect( hidden.has( 'weight' ) ).toBe( true );
	} );

	it( 'setHidden filters pinned columns', () => {
		useColumnVisibilityStore.getState().setHidden( [
			'select',
			'id',
			'description',
		] );
		const { hidden } = useColumnVisibilityStore.getState();
		expect( hidden.has( 'select' ) ).toBe( false );
		expect( hidden.has( 'id' ) ).toBe( false );
		expect( hidden.has( 'description' ) ).toBe( true );
	} );
} );

describe( 'useColumnVisibilityStore error handling', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		resetStore();
	} );

	it( 'setError stores error message', () => {
		useColumnVisibilityStore.getState().setError( 'Network failure' );
		expect( useColumnVisibilityStore.getState().error ).toBe( 'Network failure' );
	} );

	it( 'setError clears error when passed null', () => {
		useColumnVisibilityStore.getState().setError( 'err' );
		useColumnVisibilityStore.getState().setError( null );
		expect( useColumnVisibilityStore.getState().error ).toBeNull();
	} );
} );

describe( 'fetchColumnVisibility mock behavior', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		resetStore();
	} );

	it( 'resolves with hidden columns and version when called', async () => {
		mockFetchColumnVisibility.mockResolvedValueOnce( {
			hidden: [ 'description', 'weight' ],
			version: 1,
		} );

		const result = await fetchColumnVisibility();
		expect( result.hidden ).toEqual( [ 'description', 'weight' ] );
		expect( result.version ).toBe( 1 );
	} );

	it( 'resolves with empty hidden array by default', async () => {
		mockFetchColumnVisibility.mockResolvedValueOnce( {
			hidden: [],
			version: 1,
		} );

		const result = await fetchColumnVisibility();
		expect( result.hidden ).toEqual( [] );
	} );
} );

describe( 'updateColumnVisibility mock behavior', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		resetStore();
	} );

	it( 'is called with an array of hidden column IDs', async () => {
		mockUpdateColumnVisibility.mockResolvedValueOnce( {
			hidden: [ 'description' ],
			version: 1,
		} );

		await updateColumnVisibility( [ 'description' ] );
		expect( mockUpdateColumnVisibility ).toHaveBeenCalledWith( [
			'description',
		] );
	} );
} );

describe( 'resetColumnVisibility mock behavior', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		resetStore();
	} );

	it( 'resolves with empty hidden array', async () => {
		mockResetColumnVisibility.mockResolvedValueOnce( {
			hidden: [],
			version: 1,
		} );

		const result = await resetColumnVisibility();
		expect( result.hidden ).toEqual( [] );
	} );
} );
