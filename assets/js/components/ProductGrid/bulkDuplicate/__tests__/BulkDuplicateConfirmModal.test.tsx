// @vitest-environment jsdom

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';

// ---------------------------------------------------------------------------
// Mock @/hooks/useBulkDuplicate so the modal sees a controllable mutation.
// Each test mutates `mockState` *before* rendering to drive the branch under
// test; `mockDuplicate` lets the test capture the payload passed to the hook.
// ---------------------------------------------------------------------------
type HookState = {
	isDuplicating: boolean;
	lastResult:
		| undefined
		| {
				results: Array< Record< string, unknown > >;
				total: number;
				success: number;
				errors: number;
		  };
	error: Error | null;
};

const mockState: HookState = {
	isDuplicating: false,
	lastResult: undefined,
	error: null,
};

const mockDuplicate = vi.fn();
const mockReset = vi.fn();

vi.mock( '@/hooks/useBulkDuplicate', () => ( {
	useBulkDuplicate: () => ( {
		isDuplicating: mockState.isDuplicating,
		lastResult: mockState.lastResult,
		error: mockState.error,
		duplicateProducts: mockDuplicate,
		reset: mockReset,
	} ),
} ) );

// `@wordpress/i18n` is available, so we don't mock it. The component uses
// `__()`, `_n()`, `sprintf` — all of which return the source string with
// substitutions when no domain is loaded, so we can assert against the
// English templates directly.

import { BulkDuplicateConfirmModal } from '../BulkDuplicateConfirmModal';
import type { BulkDuplicateResponse } from '@/types/api';

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

	mockState.isDuplicating = false;
	mockState.lastResult = undefined;
	mockState.error = null;
	mockDuplicate.mockReset();
	mockDuplicate.mockResolvedValue( {
		results: [],
		total: 0,
		success: 0,
		errors: 0,
	} );
	mockReset.mockReset();
} );

afterEach( () => {
	act( () => {
		root.unmount();
	} );
	container.remove();
	vi.restoreAllMocks();
} );

function getTitle(): string {
	const heading = container.querySelector( '#iwbe-bulk-duplicate-title' );
	return heading?.textContent ?? '';
}

function getConfirmButton(): HTMLButtonElement {
	const buttons = container.querySelectorAll< HTMLButtonElement >(
		'button.button-primary'
	);
	if ( buttons.length === 0 ) {
		throw new Error( 'Confirm button not found' );
	}
	return buttons[ 0 ];
}

function getCheckbox( label: 'meta' | 'images' ): HTMLInputElement {
	const checkboxes = container.querySelectorAll< HTMLInputElement >(
		'input[type="checkbox"]'
	);
	// The modal renders meta first, then images (matches BulkDuplicateConfirmModal.tsx).
	if ( checkboxes.length < 2 ) {
		throw new Error( 'Expected 2 checkboxes' );
	}
	return label === 'meta' ? checkboxes[ 0 ] : checkboxes[ 1 ];
}

describe( 'BulkDuplicateConfirmModal', () => {
	it( 'renders the count in the title for a single product', () => {
		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1 ] }
				selectedCount={ 1 }
				onClose={ vi.fn() }
				onDuplicated={ vi.fn() }
			/>
		);

		const title = getTitle();
		// Singular form contains the count.
		expect( title ).toContain( '1' );
		expect( title.toLowerCase() ).toContain( 'duplicate' );
	} );

	it( 'renders the count in the title for multiple products', () => {
		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1, 2, 3, 4, 5 ] }
				selectedCount={ 5 }
				onClose={ vi.fn() }
				onDuplicated={ vi.fn() }
			/>
		);

		const title = getTitle();
		expect( title ).toContain( '5' );
		expect( title.toLowerCase() ).toContain( 'duplicate' );
	} );

	it( 'disables the confirm button when no rows are selected', () => {
		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [] }
				selectedCount={ 0 }
				onClose={ vi.fn() }
				onDuplicated={ vi.fn() }
			/>
		);

		expect( getConfirmButton().disabled ).toBe( true );
	} );

	it( 'disables the confirm button while duplicating is in flight', () => {
		mockState.isDuplicating = true;

		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1 ] }
				selectedCount={ 1 }
				onClose={ vi.fn() }
				onDuplicated={ vi.fn() }
			/>
		);

		expect( getConfirmButton().disabled ).toBe( true );
	} );

	it( 'toggles copy_meta and copy_images checkboxes via local state', () => {
		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1 ] }
				selectedCount={ 1 }
				onClose={ vi.fn() }
				onDuplicated={ vi.fn() }
			/>
		);

		const metaBox = getCheckbox( 'meta' );
		const imagesBox = getCheckbox( 'images' );
		// Defaults are both true.
		expect( metaBox.checked ).toBe( true );
		expect( imagesBox.checked ).toBe( true );

		act( () => {
			metaBox.click();
		} );
		expect( getCheckbox( 'meta' ).checked ).toBe( false );

		act( () => {
			imagesBox.click();
		} );
		expect( getCheckbox( 'images' ).checked ).toBe( false );
	} );

	it( 'calls duplicateProducts with the selected ids and current copy flags', async () => {
		const onClose = vi.fn();
		const onDuplicated = vi.fn();
		const successResult: BulkDuplicateResponse = {
			results: [ { status: 'success', id: 1, new_id: 101 } ],
			total: 1,
			success: 1,
			errors: 0,
		};
		mockDuplicate.mockResolvedValueOnce( successResult );

		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1 ] }
				selectedCount={ 1 }
				onClose={ onClose }
				onDuplicated={ onDuplicated }
			/>
		);

		// Untoggle copy_images so we can assert the form value flows through.
		act( () => {
			getCheckbox( 'images' ).click();
		} );

		await act( async () => {
			getConfirmButton().click();
		} );

		expect( mockDuplicate ).toHaveBeenCalledTimes( 1 );
		expect( mockDuplicate ).toHaveBeenCalledWith( [ 1 ], true, false );
	} );

	it( 'on full success calls onDuplicated then onClose', async () => {
		const onClose = vi.fn();
		const onDuplicated = vi.fn();
		const successResult: BulkDuplicateResponse = {
			results: [ { status: 'success', id: 1, new_id: 101 } ],
			total: 1,
			success: 1,
			errors: 0,
		};
		mockDuplicate.mockResolvedValueOnce( successResult );

		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1 ] }
				selectedCount={ 1 }
				onClose={ onClose }
				onDuplicated={ onDuplicated }
			/>
		);

		await act( async () => {
			getConfirmButton().click();
		} );

		expect( onDuplicated ).toHaveBeenCalledTimes( 1 );
		expect( onDuplicated ).toHaveBeenCalledWith( successResult );
		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shows the partial-failure banner when lastResult.errors > 0', () => {
		mockState.lastResult = {
			results: [
				{ status: 'success', id: 1, new_id: 101 },
				{ status: 'error', id: 2, code: 'wbm_not_found' },
			],
			total: 2,
			success: 1,
			errors: 1,
		};

		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1, 2 ] }
				selectedCount={ 2 }
				onClose={ vi.fn() }
				onDuplicated={ vi.fn() }
			/>
		);

		const banner = container.querySelector( '.iwbe-bulk-error' );
		expect( banner ).not.toBeNull();
		const text = banner?.textContent ?? '';
		// The banner formats "Created 1 of 2 duplicates. Errors: 1."
		expect( text ).toContain( '1' );
		expect( text ).toContain( '2' );
	} );

	it( 'closes on Escape when not in flight', () => {
		const onClose = vi.fn();

		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1 ] }
				selectedCount={ 1 }
				onClose={ onClose }
				onDuplicated={ vi.fn() }
			/>
		);

		act( () => {
			window.dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'Escape' } )
			);
		} );

		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does NOT close on Escape while duplicating', () => {
		mockState.isDuplicating = true;
		const onClose = vi.fn();

		render(
			<BulkDuplicateConfirmModal
				selectedIds={ [ 1 ] }
				selectedCount={ 1 }
				onClose={ onClose }
				onDuplicated={ vi.fn() }
			/>
		);

		act( () => {
			window.dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'Escape' } )
			);
		} );

		expect( onClose ).not.toHaveBeenCalled();
	} );
} );
