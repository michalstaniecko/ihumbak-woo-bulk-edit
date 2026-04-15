// @vitest-environment jsdom

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import type { Field, FilterGroup, FilterCondition } from '@/types/api';

// ---------------------------------------------------------------------------
// Mock @wordpress/i18n so translation calls are no-ops in tests.
// ---------------------------------------------------------------------------
vi.mock( '@wordpress/i18n', () => ( {
	__: ( s: string ) => s,
	_x: ( s: string ) => s,
} ) );

// ---------------------------------------------------------------------------
// Mock useTaxonomyTerms so TaxonomyTermPicker renders without real queries.
// ---------------------------------------------------------------------------

type MockTermsState = {
	isLoading: boolean;
	isError: boolean;
	data: { items: Array< { id: number; name: string; slug: string; count: number; parent: number } >; total: number } | undefined;
};

const mockTermsState: MockTermsState = { isLoading: false, isError: false, data: undefined };
const mockLabelMap: Record< string, string > = {};

vi.mock( '@/hooks/useTaxonomyTerms', () => ( {
	useTaxonomyTerms: () => mockTermsState,
	useTaxonomyTermLabels: () => mockLabelMap,
} ) );

// Stub Popover API for JSDOM.
let _origShowPopover: ( typeof HTMLElement.prototype )[ 'showPopover' ] | undefined;
let _origHidePopover: ( typeof HTMLElement.prototype )[ 'hidePopover' ] | undefined;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const FIELDS: Field[] = [
	{
		key: 'name',
		label: 'Name',
		type: 'text',
		editable: true,
		sortable: true,
		filterable: true,
		options: {},
	},
	{
		key: 'regular_price',
		label: 'Regular Price',
		type: 'price',
		editable: true,
		sortable: true,
		filterable: true,
		options: {},
	},
	{
		key: 'status',
		label: 'Status',
		type: 'select',
		editable: true,
		sortable: true,
		filterable: true,
		options: { publish: 'Published', draft: 'Draft' },
	},
	{
		key: 'categories',
		label: 'Categories',
		type: 'taxonomy',
		editable: true,
		sortable: false,
		filterable: true,
		options: {},
	},
];

let container: HTMLDivElement;
let root: Root;

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );

	_origShowPopover = HTMLElement.prototype.showPopover;
	_origHidePopover = HTMLElement.prototype.hidePopover;
	HTMLElement.prototype.showPopover = vi.fn( function ( this: HTMLElement ) {
		this.setAttribute( 'data-popover-open', 'true' );
	} );
	HTMLElement.prototype.hidePopover = vi.fn( function ( this: HTMLElement ) {
		this.removeAttribute( 'data-popover-open' );
	} );

	mockTermsState.isLoading = false;
	mockTermsState.isError = false;
	mockTermsState.data = undefined;
} );

afterEach( () => {
	act( () => {
		root?.unmount();
	} );
	container.remove();

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

// ---------------------------------------------------------------------------
// Lazy import of the component AFTER mocks are set up.
// ---------------------------------------------------------------------------

async function renderGroupBuilder( group: FilterGroup, props?: Partial< React.ComponentProps< typeof import('../index').FilterGroupBuilder > > ) {
	const { FilterGroupBuilder } = await import( '../index' );
	const defaultProps = {
		group,
		path: '',
		depth: 0,
		maxDepth: 3,
		fields: FIELDS,
		onAddCondition: vi.fn(),
		onAddGroup: vi.fn(),
		onRemoveNode: vi.fn(),
		onUpdateCondition: vi.fn(),
		onSetCombinator: vi.fn(),
	};
	const mergedProps = { ...defaultProps, ...props };

	await act( async () => {
		root = createRoot( container );
		root.render( <FilterGroupBuilder { ...( mergedProps as Parameters< typeof FilterGroupBuilder >[ 0 ] ) } /> );
	} );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe( 'FilterGroupBuilder', () => {
	it( 'renders condition row for a flat AND group', async () => {
		const group: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [
				{ type: 'condition', field: 'name', operator: '=', value: 'shirt' } satisfies FilterCondition,
			],
		};

		await renderGroupBuilder( group );

		// Should render one condition row — look for the field name.
		const html = container.innerHTML;
		expect( html ).toContain( 'name' );
	} );

	it( 'renders AND/OR toggle with current combinator', async () => {
		const group: FilterGroup = {
			type: 'group',
			combinator: 'OR',
			children: [],
		};

		await renderGroupBuilder( group );

		const html = container.innerHTML;
		// The combinator toggle should mention OR.
		expect( html ).toMatch( /OR/i );
	} );

	it( 'renders nested group with additional indentation class', async () => {
		const inner: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [],
		};
		const group: FilterGroup = {
			type: 'group',
			combinator: 'AND',
			children: [ inner ],
		};

		await renderGroupBuilder( group );

		// Should find the nested group element.
		const nestedGroups = container.querySelectorAll( '.iwbe-filter-group' );
		expect( nestedGroups.length ).toBeGreaterThanOrEqual( 2 );
	} );

	it( 'calls onAddCondition when add condition button is clicked', async () => {
		const onAddCondition = vi.fn();
		const group: FilterGroup = { type: 'group', combinator: 'AND', children: [] };

		await renderGroupBuilder( group, { onAddCondition } );

		const btn = container.querySelector( '.iwbe-filter-add-condition-btn' ) as HTMLButtonElement | null;
		expect( btn ).not.toBeNull();

		await act( async () => {
			btn!.click();
		} );

		expect( onAddCondition ).toHaveBeenCalledWith( '' );
	} );

	it( 'calls onAddGroup when add group button is clicked', async () => {
		const onAddGroup = vi.fn();
		const group: FilterGroup = { type: 'group', combinator: 'AND', children: [] };

		await renderGroupBuilder( group, { onAddGroup } );

		const btn = container.querySelector( '.iwbe-filter-add-group-btn' ) as HTMLButtonElement | null;
		expect( btn ).not.toBeNull();
		expect( ( btn as HTMLButtonElement ).disabled ).toBe( false );

		await act( async () => {
			btn!.click();
		} );

		expect( onAddGroup ).toHaveBeenCalledWith( '' );
	} );

	it( 'disables add group button at maxDepth', async () => {
		const group: FilterGroup = { type: 'group', combinator: 'AND', children: [] };

		await renderGroupBuilder( group, { depth: 3, maxDepth: 3 } );

		const btn = container.querySelector( '.iwbe-filter-add-group-btn' ) as HTMLButtonElement | null;
		expect( btn ).not.toBeNull();
		expect( ( btn as HTMLButtonElement ).disabled ).toBe( true );
	} );
} );

describe( 'FilterConditionRow', () => {
	async function renderConditionRow( condition: FilterCondition, props?: object ) {
		const { FilterConditionRow } = await import( '../FilterConditionRow' );
		const defaultProps = {
			condition,
			path: '0',
			fields: FIELDS,
			onUpdate: vi.fn(),
			onRemove: vi.fn(),
		};
		const mergedProps = { ...defaultProps, ...props };

		await act( async () => {
			root = createRoot( container );
			root.render( <FilterConditionRow { ...( mergedProps as Parameters< typeof FilterConditionRow >[ 0 ] ) } /> );
		} );
	}

	it( 'shows tag input for IN operator', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'regular_price',
			operator: 'IN',
			value: '10,20',
		} );

		const textarea = container.querySelector( 'textarea, .iwbe-tag-input' );
		expect( textarea ).not.toBeNull();
	} );

	it( 'shows two inputs for BETWEEN operator', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'regular_price',
			operator: 'BETWEEN',
			value: '10,20',
		} );

		const inputs = container.querySelectorAll( 'input[type="number"], input[type="text"]' );
		expect( inputs.length ).toBeGreaterThanOrEqual( 2 );
	} );

	it( 'hides value input for IS EMPTY operator', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'name',
			operator: 'IS EMPTY',
		} );

		const input = container.querySelector( 'input' );
		const textarea = container.querySelector( 'textarea' );
		// Neither a text/number input nor a textarea should be present.
		expect( input ).toBeNull();
		expect( textarea ).toBeNull();
	} );

	it( 'hides value input for IS NOT EMPTY operator', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'name',
			operator: 'IS NOT EMPTY',
		} );

		const input = container.querySelector( 'input' );
		const textarea = container.querySelector( 'textarea' );
		expect( input ).toBeNull();
		expect( textarea ).toBeNull();
	} );

	it( 'calls onRemove when remove button is clicked', async () => {
		const onRemove = vi.fn();
		await renderConditionRow(
			{ type: 'condition', field: 'name', operator: '=', value: 'shirt' },
			{ onRemove }
		);

		const btn = container.querySelector( '.iwbe-condition-remove-btn' ) as HTMLButtonElement | null;
		expect( btn ).not.toBeNull();

		await act( async () => {
			btn!.click();
		} );

		expect( onRemove ).toHaveBeenCalledWith( '0' );
	} );

	// ── Taxonomy field — reopening modal (bug fix) ────────────────────────────

	it( 'renders TaxonomyTermPicker for taxonomy field with = operator', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'categories',
			operator: '=',
			value: '42',
		} );

		const picker = container.querySelector( '.iwbe-taxonomy-picker' );
		expect( picker ).not.toBeNull();
	} );

	it( 'does NOT open the popover when condition already has a numeric term ID (modal reopen)', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'categories',
			operator: '=',
			value: '42',
		} );

		const panel = container.querySelector( '.iwbe-taxonomy-picker-panel-wrapper' );
		expect( panel ).not.toBeNull();
		// Panel must NOT be open — the popover API mock sets data-popover-open when showPopover is called.
		expect( panel?.getAttribute( 'data-popover-open' ) ).toBeNull();
	} );

	it( 'shows resolved term label in picker input when labelMap provides a name', async () => {
		// Simulate the label map returning a name for this term ID.
		mockLabelMap[ 'categories:42' ] = 'Shirts';

		await renderConditionRow( {
			type: 'condition',
			field: 'categories',
			operator: '=',
			value: '42',
		} );

		const input = container.querySelector< HTMLInputElement >( '.iwbe-taxonomy-picker-input' );
		expect( input ).not.toBeNull();
		expect( input?.value ).toBe( 'Shirts' );

		// Cleanup label map.
		delete mockLabelMap[ 'categories:42' ];
	} );

	it( 'shows "#ID" fallback in picker input when labelMap has no entry', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'categories',
			operator: '=',
			value: '99',
		} );

		const input = container.querySelector< HTMLInputElement >( '.iwbe-taxonomy-picker-input' );
		expect( input ).not.toBeNull();
		expect( input?.value ).toBe( '#99' );
	} );

	it( 'opens the popover when condition has no term ID (fresh condition)', async () => {
		await renderConditionRow( {
			type: 'condition',
			field: 'categories',
			operator: '=',
			value: '',
		} );

		const panel = container.querySelector( '.iwbe-taxonomy-picker-panel-wrapper' );
		expect( panel?.getAttribute( 'data-popover-open' ) ).toBe( 'true' );
	} );
} );
