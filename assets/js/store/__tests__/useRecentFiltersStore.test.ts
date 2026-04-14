import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock the persist middleware to avoid localStorage in tests
vi.mock( 'zustand/middleware', async ( importOriginal ) => {
	const actual = await importOriginal< typeof import( 'zustand/middleware' ) >();
	return {
		...actual,
		persist: ( config: unknown ) => config,
	};
} );

// Import AFTER mocking persist
const { useRecentFiltersStore } = await import( '../useRecentFiltersStore' );

function getState() {
	return useRecentFiltersStore.getState();
}

function reset() {
	useRecentFiltersStore.setState( { recents: [] } );
}

const baseDefinition = {
	filters: [],
	search: '',
	sort: { field: 'name', order: 'asc' as const },
};

describe( 'useRecentFiltersStore', () => {
	beforeEach( () => {
		reset();
	} );

	it( 'touch adds new entry to the top', () => {
		getState().touch( {
			id: 1,
			name: 'Filter A',
			definition: baseDefinition,
			usedAt: 1000,
		} );

		getState().touch( {
			id: 2,
			name: 'Filter B',
			definition: baseDefinition,
			usedAt: 2000,
		} );

		const { recents } = getState();
		expect( recents ).toHaveLength( 2 );
		// Most recently touched is first
		expect( recents[ 0 ].id ).toBe( 2 );
		expect( recents[ 1 ].id ).toBe( 1 );
	} );

	it( 'touch updates existing entry by id and moves to top', () => {
		getState().touch( {
			id: 1,
			name: 'Filter A',
			definition: baseDefinition,
			usedAt: 1000,
		} );
		getState().touch( {
			id: 2,
			name: 'Filter B',
			definition: baseDefinition,
			usedAt: 2000,
		} );

		// Touch id=1 again — should move to top
		getState().touch( {
			id: 1,
			name: 'Filter A (updated)',
			definition: { ...baseDefinition, search: 'new' },
			usedAt: 3000,
		} );

		const { recents } = getState();
		expect( recents ).toHaveLength( 2 );
		expect( recents[ 0 ].id ).toBe( 1 );
		expect( recents[ 0 ].name ).toBe( 'Filter A (updated)' );
		expect( recents[ 0 ].usedAt ).toBe( 3000 );
	} );

	it( 'touch trims to 5 entries', () => {
		for ( let i = 1; i <= 6; i++ ) {
			getState().touch( {
				id: i,
				name: `Filter ${ i }`,
				definition: baseDefinition,
				usedAt: i * 1000,
			} );
		}

		const { recents } = getState();
		expect( recents ).toHaveLength( 5 );
		// Oldest (id=1) should be dropped
		expect( recents.map( ( r ) => r.id ) ).not.toContain( 1 );
		// Most recent (id=6) is at top
		expect( recents[ 0 ].id ).toBe( 6 );
	} );

	it( 'removeById drops matching entry', () => {
		getState().touch( {
			id: 1,
			name: 'Filter A',
			definition: baseDefinition,
			usedAt: 1000,
		} );
		getState().touch( {
			id: 2,
			name: 'Filter B',
			definition: baseDefinition,
			usedAt: 2000,
		} );

		getState().removeById( 1 );

		const { recents } = getState();
		expect( recents ).toHaveLength( 1 );
		expect( recents[ 0 ].id ).toBe( 2 );
	} );

	it( 'removeById is a no-op for non-existing id', () => {
		getState().touch( {
			id: 1,
			name: 'Filter A',
			definition: baseDefinition,
			usedAt: 1000,
		} );

		getState().removeById( 999 );

		expect( getState().recents ).toHaveLength( 1 );
	} );

	it( 'clear empties list', () => {
		getState().touch( {
			id: 1,
			name: 'Filter A',
			definition: baseDefinition,
			usedAt: 1000,
		} );
		getState().touch( {
			id: 2,
			name: 'Filter B',
			definition: baseDefinition,
			usedAt: 2000,
		} );

		getState().clear();

		expect( getState().recents ).toHaveLength( 0 );
	} );
} );
