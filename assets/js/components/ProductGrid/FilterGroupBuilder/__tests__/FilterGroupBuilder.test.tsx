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
];

let container: HTMLDivElement;
let root: Root;

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
} );

afterEach( () => {
	act( () => {
		root?.unmount();
	} );
	container.remove();
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
} );
