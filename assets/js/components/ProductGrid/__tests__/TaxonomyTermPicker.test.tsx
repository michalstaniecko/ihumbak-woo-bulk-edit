// @vitest-environment jsdom

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';

// ---------------------------------------------------------------------------
// Mock useTaxonomyTerms hook so we can control loading/error/data states.
// ---------------------------------------------------------------------------

type MockTermsState = {
	isLoading: boolean;
	isError: boolean;
	data: {
		items: Array< { id: number; name: string; slug: string; count: number; parent: number } >;
		total: number;
	} | undefined;
};

const mockTermsState: MockTermsState = {
	isLoading: false,
	isError: false,
	data: undefined,
};

vi.mock( '@/hooks/useTaxonomyTerms', () => ( {
	useTaxonomyTerms: () => mockTermsState,
	useTaxonomyTermLabels: () => ( {} ),
} ) );

import { TaxonomyTermPicker } from '../TaxonomyTermPicker';
import type { TaxonomyTermDetail } from '@/types/api';

let container: HTMLDivElement;
let root: Root;

function render( element: React.ReactElement ): void {
	act( () => {
		root.render( element );
	} );
}

function resetMockState(): void {
	mockTermsState.isLoading = false;
	mockTermsState.isError = false;
	mockTermsState.data = undefined;
}

// Popover API stubs — stored so we can restore them after each test.
let _origShowPopover: ( typeof HTMLElement.prototype )[ 'showPopover' ] | undefined;
let _origHidePopover: ( typeof HTMLElement.prototype )[ 'hidePopover' ] | undefined;

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	resetMockState();

	// Mock Popover API — JSDOM does not implement it yet.
	_origShowPopover = HTMLElement.prototype.showPopover;
	_origHidePopover = HTMLElement.prototype.hidePopover;
	HTMLElement.prototype.showPopover = vi.fn( function ( this: HTMLElement ) {
		this.setAttribute( 'data-popover-open', 'true' );
	} );
	HTMLElement.prototype.hidePopover = vi.fn( function ( this: HTMLElement ) {
		this.removeAttribute( 'data-popover-open' );
	} );
} );

afterEach( () => {
	act( () => {
		root.unmount();
	} );
	container.remove();
	// Restore Popover API stubs so each test gets a fresh vi.fn().
	if ( _origShowPopover !== undefined ) {
		HTMLElement.prototype.showPopover = _origShowPopover;
	} else {
		delete ( HTMLElement.prototype as Partial< HTMLElement > ).showPopover;
	}
	if ( _origHidePopover !== undefined ) {
		HTMLElement.prototype.hidePopover = _origHidePopover;
	} else {
		delete ( HTMLElement.prototype as Partial< HTMLElement > ).hidePopover;
	}
	vi.restoreAllMocks();
} );

const mockTerms: TaxonomyTermDetail[] = [
	{ id: 1, name: 'Shirts', slug: 'shirts', count: 5, parent: 0 },
	{ id: 2, name: 'Hats', slug: 'hats', count: 3, parent: 0 },
	{ id: 3, name: 'Shoes', slug: 'shoes', count: 1, parent: 0 },
];

function getInput(): HTMLInputElement {
	const input = container.querySelector< HTMLInputElement >(
		'.iwbe-taxonomy-picker-input'
	);
	if ( ! input ) throw new Error( 'Picker input not found' );
	return input;
}

// The panel wrapper is a popover element — in real browsers it would be in the
// top-layer (still accessible via document.querySelector). In JSDOM it stays in
// the normal DOM tree as a child of the component root, so querying document
// covers both cases.
function getPanelWrapper(): HTMLElement | null {
	return document.querySelector( '.iwbe-taxonomy-picker-panel-wrapper' );
}

function getDropdown(): HTMLElement | null {
	return document.querySelector( '.iwbe-taxonomy-picker-dropdown' );
}

function getItems(): NodeListOf< HTMLElement > {
	return document.querySelectorAll< HTMLElement >(
		'.iwbe-taxonomy-picker-item'
	);
}

function getLoadingIndicator(): HTMLElement | null {
	return document.querySelector( '.iwbe-taxonomy-picker-loading' );
}

function getEmptyState(): HTMLElement | null {
	return document.querySelector( '.iwbe-taxonomy-picker-empty' );
}

function getErrorState(): HTMLElement | null {
	return document.querySelector( '.iwbe-taxonomy-picker-error' );
}

describe( 'TaxonomyTermPicker', () => {
	// ── Basic rendering ───────────────────────────────────────────────────────

	it( 'renders without crashing', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect( container.querySelector( '.iwbe-taxonomy-picker' ) ).not.toBeNull();
	} );

	it( 'renders a text input', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const input = getInput();
		expect( input ).not.toBeNull();
		expect( input.type ).toBe( 'text' );
	} );

	// ── Popover API ───────────────────────────────────────────────────────────

	it( 'invokes showPopover on the panel element on mount', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const panel = getPanelWrapper();
		expect( panel ).not.toBeNull();
		expect( panel?.getAttribute( 'data-popover-open' ) ).toBe( 'true' );
	} );

	it( 'calls onCancel when the popover emits a "closed" toggle event', () => {
		const onCancel = vi.fn();
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ onCancel }
			/>
		);

		const panel = getPanelWrapper();
		act( () => {
			const evt = new Event( 'toggle' );
			Object.defineProperty( evt, 'newState', { value: 'closed' } );
			panel!.dispatchEvent( evt );
		} );

		expect( onCancel ).toHaveBeenCalledTimes( 1 );
	} );

	// ── Loading state ─────────────────────────────────────────────────────────

	it( 'shows loading indicator when isLoading is true', () => {
		mockTermsState.isLoading = true;

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect( getLoadingIndicator() ).not.toBeNull();
	} );

	// ── Error state ───────────────────────────────────────────────────────────

	it( 'shows error state when isError is true', () => {
		mockTermsState.isError = true;

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect( getErrorState() ).not.toBeNull();
	} );

	// ── Empty state ───────────────────────────────────────────────────────────

	it( 'shows empty state when data has no items', () => {
		mockTermsState.data = { items: [], total: 0 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect( getEmptyState() ).not.toBeNull();
	} );

	// ── Items rendering ───────────────────────────────────────────────────────

	it( 'renders term items when data is available', () => {
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const items = getItems();
		expect( items.length ).toBe( 3 );
	} );

	it( 'displays term names in items', () => {
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const text = document.body.textContent ?? '';
		expect( text ).toContain( 'Shirts' );
		expect( text ).toContain( 'Hats' );
		expect( text ).toContain( 'Shoes' );
	} );

	// ── Selection callback ────────────────────────────────────────────────────

	it( 'calls onSelect with the term when an item is mousedown-ed', () => {
		const onSelect = vi.fn();
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ onSelect }
				onCancel={ vi.fn() }
			/>
		);

		const items = getItems();
		act( () => {
			items[ 0 ].dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		} );

		expect( onSelect ).toHaveBeenCalledTimes( 1 );
		expect( onSelect ).toHaveBeenCalledWith( mockTerms[ 0 ] );
	} );

	it( 'does not call onSelect when an item is just hovered (mousemove)', () => {
		const onSelect = vi.fn();
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ onSelect }
				onCancel={ vi.fn() }
			/>
		);

		const items = getItems();
		act( () => {
			items[ 0 ].dispatchEvent( new MouseEvent( 'mousemove', { bubbles: true } ) );
		} );

		expect( onSelect ).not.toHaveBeenCalled();
	} );

	// ── Escape key ────────────────────────────────────────────────────────────

	it( 'calls onCancel when Escape is pressed on the input', () => {
		const onCancel = vi.fn();

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ onCancel }
			/>
		);

		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } )
			);
		} );

		expect( onCancel ).toHaveBeenCalledTimes( 1 );
	} );

	// ── Keyboard navigation ───────────────────────────────────────────────────

	it( 'highlights the first item after ArrowDown from the input', () => {
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowDown', bubbles: true } )
			);
		} );

		const items = getItems();
		const activeItem = document.querySelector(
			'.iwbe-taxonomy-picker-item--active'
		);
		expect( activeItem ).not.toBeNull();
		expect( activeItem?.textContent ).toContain( mockTerms[ 0 ].name );
		// Only the first item should be highlighted.
		expect( items[ 0 ].classList.contains( 'iwbe-taxonomy-picker-item--active' ) ).toBe( true );
	} );

	it( 'moves highlight down on consecutive ArrowDown', () => {
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowDown', bubbles: true } )
			);
		} );
		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowDown', bubbles: true } )
			);
		} );

		const items = getItems();
		expect( items[ 1 ].classList.contains( 'iwbe-taxonomy-picker-item--active' ) ).toBe( true );
	} );

	it( 'wraps from last to first on ArrowDown', () => {
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		// Press down 4 times (3 items → wraps to first).
		for ( let i = 0; i < 4; i++ ) {
			act( () => {
				getInput().dispatchEvent(
					new KeyboardEvent( 'keydown', {
						key: 'ArrowDown',
						bubbles: true,
					} )
				);
			} );
		}

		const items = getItems();
		expect( items[ 0 ].classList.contains( 'iwbe-taxonomy-picker-item--active' ) ).toBe( true );
	} );

	it( 'selects the highlighted item on Enter', () => {
		const onSelect = vi.fn();
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ onSelect }
				onCancel={ vi.fn() }
			/>
		);

		// Arrow down to first item then Enter.
		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowDown', bubbles: true } )
			);
		} );
		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } )
			);
		} );

		expect( onSelect ).toHaveBeenCalledWith( mockTerms[ 0 ] );
	} );

	it( 'ArrowUp from first item wraps to last', () => {
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		// Go down to first, then up to wrap to last.
		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowDown', bubbles: true } )
			);
		} );
		act( () => {
			getInput().dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowUp', bubbles: true } )
			);
		} );

		const items = getItems();
		expect(
			items[ items.length - 1 ].classList.contains( 'iwbe-taxonomy-picker-item--active' )
		).toBe( true );
	} );

	// ── Dropdown visibility ───────────────────────────────────────────────────

	it( 'shows a dropdown when data is present', () => {
		mockTermsState.data = { items: mockTerms, total: 3 };

		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect( getDropdown() ).not.toBeNull();
	} );

	// ── selectedTermId prop — collapsed initial state ─────────────────────────

	it( 'does NOT call showPopover when selectedTermId is provided on mount', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				selectedTermId="42"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const panel = getPanelWrapper();
		expect( panel?.getAttribute( 'data-popover-open' ) ).toBeNull();
	} );

	it( 'does NOT call showPopover when selectedLabel is provided on mount', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				selectedLabel="Shirts"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const panel = getPanelWrapper();
		expect( panel?.getAttribute( 'data-popover-open' ) ).toBeNull();
	} );

	it( 'shows selectedLabel in the input when collapsed via selectedTermId', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				selectedTermId="42"
				selectedLabel="Shirts"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const input = getInput();
		expect( input.value ).toBe( 'Shirts' );
	} );

	it( 'shows "#ID" fallback in input when selectedTermId is set but selectedLabel is empty', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				selectedTermId="42"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		const input = getInput();
		expect( input.value ).toBe( '#42' );
	} );

	it( 'collapses panel when selectedTermId changes from empty to a value', () => {
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				selectedTermId=""
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		// Panel open initially (no selection).
		expect( getPanelWrapper()?.getAttribute( 'data-popover-open' ) ).toBe( 'true' );

		// Re-render with a term ID set.
		render(
			<TaxonomyTermPicker
				fieldKey="categories"
				selectedTermId="7"
				onSelect={ vi.fn() }
				onCancel={ vi.fn() }
			/>
		);

		expect( getPanelWrapper()?.getAttribute( 'data-popover-open' ) ).toBeNull();
	} );
} );
