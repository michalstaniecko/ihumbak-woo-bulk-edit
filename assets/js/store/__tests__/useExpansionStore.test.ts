import { describe, it, expect, beforeEach } from 'vitest';
import { useExpansionStore } from '../useExpansionStore';

function getState() {
	return useExpansionStore.getState();
}

function reset() {
	useExpansionStore.setState( {
		expanded: new Set< number >(),
	} );
}

describe( 'useExpansionStore', () => {
	beforeEach( () => {
		reset();
	} );

	describe( 'isExpanded', () => {
		it( 'returns false for an ID that has not been expanded', () => {
			expect( getState().isExpanded( 1 ) ).toBe( false );
		} );

		it( 'returns true after expand is called', () => {
			getState().expand( 1 );
			expect( getState().isExpanded( 1 ) ).toBe( true );
		} );

		it( 'returns false after collapse is called', () => {
			getState().expand( 1 );
			getState().collapse( 1 );
			expect( getState().isExpanded( 1 ) ).toBe( false );
		} );
	} );

	describe( 'toggle', () => {
		it( 'expands a collapsed product', () => {
			getState().toggle( 5 );
			expect( getState().isExpanded( 5 ) ).toBe( true );
		} );

		it( 'collapses an expanded product', () => {
			getState().expand( 5 );
			getState().toggle( 5 );
			expect( getState().isExpanded( 5 ) ).toBe( false );
		} );

		it( 'handles multiple independent IDs', () => {
			getState().toggle( 1 );
			getState().toggle( 2 );
			getState().toggle( 1 );

			expect( getState().isExpanded( 1 ) ).toBe( false );
			expect( getState().isExpanded( 2 ) ).toBe( true );
		} );
	} );

	describe( 'expand', () => {
		it( 'is idempotent — calling expand twice keeps state expanded', () => {
			getState().expand( 10 );
			getState().expand( 10 );
			expect( getState().isExpanded( 10 ) ).toBe( true );
		} );
	} );

	describe( 'collapse', () => {
		it( 'is a no-op when the product is not expanded', () => {
			getState().collapse( 99 );
			expect( getState().isExpanded( 99 ) ).toBe( false );
		} );
	} );

	describe( 'clear', () => {
		it( 'collapses all expanded products', () => {
			getState().expand( 1 );
			getState().expand( 2 );
			getState().expand( 3 );

			getState().clear();

			expect( getState().isExpanded( 1 ) ).toBe( false );
			expect( getState().isExpanded( 2 ) ).toBe( false );
			expect( getState().isExpanded( 3 ) ).toBe( false );
		} );

		it( 'is safe to call when nothing is expanded', () => {
			getState().clear();
			expect( getState().isExpanded( 1 ) ).toBe( false );
		} );
	} );

	describe( 'expanded set integrity', () => {
		it( 'tracks multiple different IDs independently', () => {
			getState().expand( 1 );
			getState().expand( 2 );
			getState().expand( 3 );

			expect( getState().isExpanded( 1 ) ).toBe( true );
			expect( getState().isExpanded( 2 ) ).toBe( true );
			expect( getState().isExpanded( 3 ) ).toBe( true );
			expect( getState().isExpanded( 4 ) ).toBe( false );
		} );
	} );
} );
