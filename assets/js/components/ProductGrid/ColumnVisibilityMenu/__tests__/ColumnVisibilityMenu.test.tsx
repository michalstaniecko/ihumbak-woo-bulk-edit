// @vitest-environment jsdom

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

vi.mock( 'zustand/middleware', async ( importOriginal ) => {
	const actual = await importOriginal< typeof import( 'zustand/middleware' ) >();
	return {
		...actual,
		persist: ( config: unknown ) => config,
	};
} );

// Mock useColumnVisibility hook so we can control it from tests
type MockVisibilityState = {
	hidden: Set< string >;
	isHydrated: boolean;
	isLoading: boolean;
	error: string | null;
	persist: ( hidden: Set< string > ) => void;
	resetServer: () => void;
};

const mockVisibilityState: MockVisibilityState = {
	hidden: new Set< string >(),
	isHydrated: true,
	isLoading: false,
	error: null,
	persist: vi.fn(),
	resetServer: vi.fn(),
};

vi.mock( '@/hooks/useColumnVisibility', () => ( {
	useColumnVisibility: () => mockVisibilityState,
} ) );

// Mock the store
const { useColumnVisibilityStore } = await import(
	'@/store/useColumnVisibilityStore'
);

// Reset the store before each test
function resetStore() {
	useColumnVisibilityStore.setState( {
		hidden: new Set< string >(),
		isHydrated: true,
		isLoading: false,
		error: null,
	} );
}

// Import component AFTER mocks
const { ColumnVisibilityMenu } = await import( '../ColumnVisibilityMenu' );
import type { Field } from '@/types/api';

// ---------------------------------------------------------------------------
// Test helpers
// ---------------------------------------------------------------------------

function makeField( key: string, label: string ): Field {
	return {
		key,
		label,
		type: 'text',
		editable: true,
		sortable: true,
		filterable: true,
		options: {},
	};
}

const testFields: Field[] = [
	makeField( 'name', 'Name' ),
	makeField( 'sku', 'SKU' ),
	makeField( 'regular_price', 'Regular Price' ),
	makeField( 'description', 'Description' ),
	makeField( 'weight', 'Weight' ),
];

let container: HTMLDivElement;
let root: Root;

function render( element: React.ReactElement ): void {
	act( () => {
		root.render( element );
	} );
}

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	resetStore();

	// Reset mock state
	mockVisibilityState.hidden = new Set< string >();
	mockVisibilityState.isHydrated = true;
	mockVisibilityState.isLoading = false;
	mockVisibilityState.error = null;
	( mockVisibilityState.persist as ReturnType< typeof vi.fn > ).mockClear();
	( mockVisibilityState.resetServer as ReturnType< typeof vi.fn > ).mockClear();
} );

afterEach( () => {
	act( () => {
		root.unmount();
	} );
	container.remove();
} );

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe( 'ColumnVisibilityMenu', () => {
	it( 'renders the trigger button', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector( '.iwbe-column-visibility-btn' );
		expect( btn ).not.toBeNull();
	} );

	it( 'dialog is not visible initially', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const dialog = container.querySelector( '.iwbe-column-visibility-dialog' );
		expect( dialog ).toBeNull();
	} );

	it( 'opens the dialog when trigger button is clicked', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const dialog = container.querySelector( '.iwbe-column-visibility-dialog' );
		expect( dialog ).not.toBeNull();
	} );

	it( 'closes the dialog when Escape is pressed', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		expect(
			container.querySelector( '.iwbe-column-visibility-dialog' )
		).not.toBeNull();

		act( () => {
			const event = new KeyboardEvent( 'keydown', {
				key: 'Escape',
				bubbles: true,
			} );
			document.dispatchEvent( event );
		} );

		const dialog = container.querySelector( '.iwbe-column-visibility-dialog' );
		expect( dialog ).toBeNull();
	} );

	it( 'shows field labels in the dialog', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const dialog = container.querySelector( '.iwbe-column-visibility-dialog' );
		expect( dialog?.textContent ).toContain( 'Name' );
		expect( dialog?.textContent ).toContain( 'Description' );
	} );

	it( 'shows counter of visible columns', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const counter = container.querySelector(
			'.iwbe-column-visibility-counter'
		);
		expect( counter ).not.toBeNull();
		// 5 fields visible, 5 total
		expect( counter?.textContent ).toContain( '5' );
	} );

	it( 'counter changes when columns are hidden', () => {
		mockVisibilityState.hidden = new Set( [ 'description', 'weight' ] );

		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const counter = container.querySelector(
			'.iwbe-column-visibility-counter'
		);
		// 3 visible out of 5
		expect( counter?.textContent ).toContain( '3' );
		expect( counter?.textContent ).toContain( '5' );
	} );

	it( 'search filters columns by label', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const searchInput = container.querySelector(
			'.iwbe-column-visibility-search'
		) as HTMLInputElement;
		expect( searchInput ).not.toBeNull();

		act( () => {
			searchInput.value = 'desc';
			searchInput.dispatchEvent(
				new Event( 'input', { bubbles: true } )
			);
		} );

		const checkboxLabels = container.querySelectorAll(
			'.iwbe-column-visibility-dialog label'
		);
		const visibleLabels = Array.from( checkboxLabels ).map(
			( el ) => el.textContent
		);

		// Only Description should match
		expect( visibleLabels.some( ( l ) => l?.includes( 'Description' ) ) ).toBe(
			true
		);
		expect( visibleLabels.some( ( l ) => l?.includes( 'Name' ) ) ).toBe( false );
	} );

	it( 'select and id columns are NOT shown in dialog', () => {
		const fieldsWithPinned: Field[] = [
			...testFields,
			makeField( 'select', 'Select' ),
			makeField( 'id', 'ID' ),
		];

		render( <ColumnVisibilityMenu fields={ fieldsWithPinned } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const checkboxes = container.querySelectorAll< HTMLInputElement >(
			'.iwbe-column-visibility-dialog input[type="checkbox"]'
		);
		const ids = Array.from( checkboxes ).map( ( cb ) => cb.dataset.columnId );

		expect( ids ).not.toContain( 'select' );
		expect( ids ).not.toContain( 'id' );
	} );

	it( 'has role="dialog" on the dialog element', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const dialog = container.querySelector( '.iwbe-column-visibility-dialog' );
		expect( dialog?.getAttribute( 'role' ) ).toBe( 'dialog' );
	} );

	it( 'calls toggle on store when checkbox is clicked', () => {
		render( <ColumnVisibilityMenu fields={ testFields } /> );
		const btn = container.querySelector(
			'.iwbe-column-visibility-btn'
		) as HTMLButtonElement;

		act( () => {
			btn.click();
		} );

		const descCheckbox = container.querySelector< HTMLInputElement >(
			'input[data-column-id="description"]'
		);
		expect( descCheckbox ).not.toBeNull();

		act( () => {
			descCheckbox!.click();
		} );

		// After toggle, persist should have been called
		expect( mockVisibilityState.persist ).toHaveBeenCalled();
	} );
} );
