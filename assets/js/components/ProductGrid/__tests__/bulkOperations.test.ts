import { describe, it, expect } from 'vitest';
import {
	applyNumericOperation,
	applySpecialEnding,
	applyTextOperation,
	applyBooleanOperation,
	applyTaxonomyOperation,
	inferBulkOperationKind,
	type SpecialEnding,
} from '../bulkOperations';
import type { Field } from '@/types/api';

function makeField( overrides: Partial< Field > = {} ): Field {
	return {
		key: 'regular_price',
		label: 'Regular price',
		type: 'price',
		editable: true,
		sortable: true,
		filterable: true,
		options: {},
		...overrides,
	} as Field;
}

describe( 'applyNumericOperation', () => {
	const priceField = makeField( { key: 'regular_price', type: 'price' } );
	const intField = makeField( { key: 'stock_quantity', type: 'integer' } );
	const numField = makeField( { key: 'weight', type: 'number' } );

	it( 'set replaces current value', () => {
		expect(
			applyNumericOperation( '10.00', { type: 'set', value: 25 }, priceField )
		).toBe( '25.00' );
	} );

	it( 'increase adds to numeric value, formats as price', () => {
		expect(
			applyNumericOperation(
				'10.00',
				{ type: 'increase', amount: 5 },
				priceField
			)
		).toBe( '15.00' );
	} );

	it( 'decrease subtracts from numeric value', () => {
		expect(
			applyNumericOperation(
				'10.00',
				{ type: 'decrease', amount: 3 },
				priceField
			)
		).toBe( '7.00' );
	} );

	it( 'increase_pct applies percentage markup', () => {
		expect(
			applyNumericOperation(
				'100.00',
				{ type: 'increase_pct', percent: 20 },
				priceField
			)
		).toBe( '120.00' );
	} );

	it( 'decrease_pct applies percentage discount', () => {
		expect(
			applyNumericOperation(
				'100.00',
				{ type: 'decrease_pct', percent: 25 },
				priceField
			)
		).toBe( '75.00' );
	} );

	it( 'round truncates to given precision', () => {
		expect(
			applyNumericOperation(
				'12.3456',
				{ type: 'round', precision: 2 },
				numField
			)
		).toBe( 12.35 );
	} );

	it( 'round with precision 0 rounds to integer', () => {
		expect(
			applyNumericOperation(
				'12.6',
				{ type: 'round', precision: 0 },
				intField
			)
		).toBe( 13 );
	} );

	it( 'clear returns empty string for price field', () => {
		expect(
			applyNumericOperation( '10.00', { type: 'clear' }, priceField )
		).toBe( '' );
	} );

	it( 'clear returns null for non-price numeric field', () => {
		expect(
			applyNumericOperation( 5, { type: 'clear' }, intField )
		).toBeNull();
	} );

	it( 'handles null/undefined current value as base 0', () => {
		expect(
			applyNumericOperation( null, { type: 'increase', amount: 7 }, numField )
		).toBe( 7 );
		expect(
			applyNumericOperation(
				undefined,
				{ type: 'increase', amount: 3 },
				numField
			)
		).toBe( 3 );
	} );

	it( 'integer field truncates fractional results', () => {
		expect(
			applyNumericOperation(
				10,
				{ type: 'increase', amount: 2.7 },
				intField
			)
		).toBe( 12 );
	} );
} );

describe( 'applyNumericOperation with explicit base', () => {
	// The modal is responsible for pre-validating regular_price before
	// passing it here; this block only covers the pure-function behavior.
	const salePriceField = makeField( { key: 'sale_price', type: 'price' } );

	it( 'increase uses explicit base instead of current value', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'increase', amount: 10 },
				salePriceField,
				100
			)
		).toBe( '110.00' );
	} );

	it( 'decrease uses explicit base instead of current value', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'decrease', amount: 10 },
				salePriceField,
				100
			)
		).toBe( '90.00' );
	} );

	it( 'increase_pct uses explicit base', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'increase_pct', percent: 20 },
				salePriceField,
				100
			)
		).toBe( '120.00' );
	} );

	it( 'decrease_pct uses explicit base (regular − 20% = sale)', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'decrease_pct', percent: 20 },
				salePriceField,
				100
			)
		).toBe( '80.00' );
	} );

	it( 'round uses explicit base', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'round', precision: 1 },
				salePriceField,
				12.3456
			)
		).toBe( '12.30' );
	} );

	it( 'set ignores explicit base', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'set', value: 25 },
				salePriceField,
				100
			)
		).toBe( '25.00' );
	} );

	it( 'clear ignores explicit base', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'clear' },
				salePriceField,
				100
			)
		).toBe( '' );
	} );

	it( 'undefined base falls back to current value', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'increase', amount: 5 },
				salePriceField,
				undefined
			)
		).toBe( '55.00' );
	} );
} );

describe( 'applyTextOperation', () => {
	it( 'set replaces value', () => {
		expect( applyTextOperation( 'old', { type: 'set', value: 'new' } ) ).toBe(
			'new'
		);
	} );

	it( 'clear returns empty string', () => {
		expect( applyTextOperation( 'anything', { type: 'clear' } ) ).toBe( '' );
	} );

	it( 'append concatenates suffix', () => {
		expect(
			applyTextOperation( 'foo', { type: 'append', value: '-bar' } )
		).toBe( 'foo-bar' );
	} );

	it( 'prepend concatenates prefix', () => {
		expect(
			applyTextOperation( 'bar', { type: 'prepend', value: 'foo-' } )
		).toBe( 'foo-bar' );
	} );

	it( 'search_replace is case-insensitive by default', () => {
		expect(
			applyTextOperation( 'Hello World', {
				type: 'search_replace',
				search: 'hello',
				replace: 'Hi',
				caseSensitive: false,
			} )
		).toBe( 'Hi World' );
	} );

	it( 'search_replace respects case sensitivity', () => {
		expect(
			applyTextOperation( 'Hello hello', {
				type: 'search_replace',
				search: 'hello',
				replace: 'Hi',
				caseSensitive: true,
			} )
		).toBe( 'Hello Hi' );
	} );

	it( 'search_replace with empty search returns unchanged', () => {
		expect(
			applyTextOperation( 'Hello', {
				type: 'search_replace',
				search: '',
				replace: 'X',
				caseSensitive: false,
			} )
		).toBe( 'Hello' );
	} );

	it( 'search_replace escapes regex special characters', () => {
		expect(
			applyTextOperation( '$10.00 (discount)', {
				type: 'search_replace',
				search: '(discount)',
				replace: '[sale]',
				caseSensitive: true,
			} )
		).toBe( '$10.00 [sale]' );
	} );

	it( 'case upper converts to uppercase', () => {
		expect(
			applyTextOperation( 'Hello World', { type: 'case', mode: 'upper' } )
		).toBe( 'HELLO WORLD' );
	} );

	it( 'case lower converts to lowercase', () => {
		expect(
			applyTextOperation( 'Hello World', { type: 'case', mode: 'lower' } )
		).toBe( 'hello world' );
	} );

	it( 'case title capitalises each word', () => {
		expect(
			applyTextOperation( 'hello world', { type: 'case', mode: 'title' } )
		).toBe( 'Hello World' );
	} );

	it( 'case sentence capitalises first letter of each sentence', () => {
		expect(
			applyTextOperation( 'hello world. foo bar!', {
				type: 'case',
				mode: 'sentence',
			} )
		).toBe( 'Hello world. Foo bar!' );
	} );

	it( 'handles null/undefined current value as empty string', () => {
		expect(
			applyTextOperation( null, { type: 'append', value: 'X' } )
		).toBe( 'X' );
		expect(
			applyTextOperation( undefined, { type: 'prepend', value: 'Y' } )
		).toBe( 'Y' );
	} );
} );

describe( 'applyBooleanOperation', () => {
	it( 'set_true returns true regardless of current', () => {
		expect( applyBooleanOperation( false, { type: 'set_true' } ) ).toBe( true );
		expect( applyBooleanOperation( true, { type: 'set_true' } ) ).toBe( true );
	} );

	it( 'set_false returns false regardless of current', () => {
		expect( applyBooleanOperation( true, { type: 'set_false' } ) ).toBe(
			false
		);
		expect( applyBooleanOperation( false, { type: 'set_false' } ) ).toBe(
			false
		);
	} );

	it( 'toggle flips value', () => {
		expect( applyBooleanOperation( true, { type: 'toggle' } ) ).toBe( false );
		expect( applyBooleanOperation( false, { type: 'toggle' } ) ).toBe( true );
	} );

	it( 'toggle coerces falsy values to true', () => {
		expect( applyBooleanOperation( null, { type: 'toggle' } ) ).toBe( true );
		expect( applyBooleanOperation( 0, { type: 'toggle' } ) ).toBe( true );
	} );
} );

describe( 'applyTaxonomyOperation', () => {
	const current = [
		{ id: 1, name: 'A' },
		{ id: 2, name: 'B' },
	];

	it( 'add appends new terms without duplicates', () => {
		expect(
			applyTaxonomyOperation( current, {
				type: 'add',
				terms: [
					{ id: 2, name: 'B' },
					{ id: 3, name: 'C' },
				],
			} )
		).toEqual( [
			{ id: 1, name: 'A' },
			{ id: 2, name: 'B' },
			{ id: 3, name: 'C' },
		] );
	} );

	it( 'remove drops matching IDs', () => {
		expect(
			applyTaxonomyOperation( current, { type: 'remove', termIds: [ 1 ] } )
		).toEqual( [ { id: 2, name: 'B' } ] );
	} );

	it( 'replace overwrites entire value', () => {
		expect(
			applyTaxonomyOperation( current, {
				type: 'replace',
				terms: [ { id: 99, name: 'Z' } ],
			} )
		).toEqual( [ { id: 99, name: 'Z' } ] );
	} );

	it( 'replace deduplicates', () => {
		expect(
			applyTaxonomyOperation( current, {
				type: 'replace',
				terms: [
					{ id: 1, name: 'A' },
					{ id: 1, name: 'A' },
					{ id: 2, name: 'B' },
				],
			} )
		).toEqual( [
			{ id: 1, name: 'A' },
			{ id: 2, name: 'B' },
		] );
	} );

	it( 'handles non-array current value', () => {
		expect(
			applyTaxonomyOperation( null, {
				type: 'add',
				terms: [ { id: 1, name: 'A' } ],
			} )
		).toEqual( [ { id: 1, name: 'A' } ] );
	} );
} );

describe( 'applySpecialEnding', () => {
	const cases: [ number, SpecialEnding, number ][] = [
		// .00 rounding — rounds UP to next integer .00 if fractional
		[ 12.34, '00', 13.00 ],
		[ 10.00, '00', 10.00 ],
		// .90 rounding
		[ 12.34, '90', 12.90 ],
		[ 9.55,  '90', 9.90  ],
		[ 9.95,  '90', 10.90 ],
		[ 9.90,  '90', 9.90  ],
		// .99 rounding
		[ 12.34, '99', 12.99 ],
		[ 9.55,  '99', 9.99  ],
		[ 9.99,  '99', 9.99  ],
		[ 10.00, '99', 10.99 ],
		[ 0.00,  '99', 0.99  ],
		// floating-point stability
		[ 0.30000000000000004, '99', 0.99 ],
		// x9.00 rounding — rounds UP to nearest integer ending in 9, zero cents
		[ 5.00,   '9_00',  9.00  ],
		[ 9.00,   '9_00',  9.00  ],   // idempotent
		[ 9.01,   '9_00', 19.00  ],
		[ 9.50,   '9_00', 19.00  ],
		[ 10.00,  '9_00', 19.00  ],
		[ 18.99,  '9_00', 19.00  ],
		[ 19.00,  '9_00', 19.00  ],   // idempotent
		[ 99.00,  '9_00', 99.00  ],   // idempotent
		[ 99.01,  '9_00', 109.00 ],
		[ 100.00, '9_00', 109.00 ],
	];

	for ( const [ input, ending, expected ] of cases ) {
		it( `applySpecialEnding(${ input }, '${ ending }') → ${ expected }`, () => {
			expect( applySpecialEnding( input, ending ) ).toBeCloseTo(
				expected,
				10
			);
		} );
	}

	it( 'none is a passthrough', () => {
		expect( applySpecialEnding( 12.34, 'none' ) ).toBeCloseTo( 12.34, 10 );
		expect( applySpecialEnding( 9.99, 'none' ) ).toBeCloseTo( 9.99, 10 );
	} );
} );

describe( 'applyNumericOperation with specialEnding', () => {
	const priceField = makeField( { key: 'regular_price', type: 'price' } );
	const salePriceField = makeField( { key: 'sale_price', type: 'price' } );

	it( 'set 12.34 with ending 99 → "12.99"', () => {
		expect(
			applyNumericOperation(
				'10.00',
				{ type: 'set', value: 12.34 },
				priceField,
				undefined,
				'99'
			)
		).toBe( '12.99' );
	} );

	it( 'increase 10.00 by 2.34 with ending 99 → "12.99"', () => {
		expect(
			applyNumericOperation(
				'10.00',
				{ type: 'increase', amount: 2.34 },
				priceField,
				undefined,
				'99'
			)
		).toBe( '12.99' );
	} );

	it( 'increase_pct 100 by 23.4 with ending 00 → "124.00"', () => {
		expect(
			applyNumericOperation(
				'100.00',
				{ type: 'increase_pct', percent: 23.4 },
				priceField,
				undefined,
				'00'
			)
		).toBe( '124.00' );
	} );

	it( 'decrease_pct 100 by 23.4 with ending 99 → "76.99"', () => {
		expect(
			applyNumericOperation(
				'100.00',
				{ type: 'decrease_pct', percent: 23.4 },
				priceField,
				undefined,
				'99'
			)
		).toBe( '76.99' );
	} );

	it( 'clear with any ending → "" (no rounding applied)', () => {
		expect(
			applyNumericOperation(
				'10.00',
				{ type: 'clear' },
				priceField,
				undefined,
				'99'
			)
		).toBe( '' );
	} );

	it( 'specialEnding none leaves price formatting unchanged', () => {
		expect(
			applyNumericOperation(
				'10.00',
				{ type: 'set', value: 12.34 },
				priceField,
				undefined,
				'none'
			)
		).toBe( '12.34' );
	} );

	it( 'specialEnding is ignored for non-price fields', () => {
		const intField = makeField( { key: 'stock_quantity', type: 'integer' } );
		expect(
			applyNumericOperation(
				10,
				{ type: 'increase', amount: 2.7 },
				intField,
				undefined,
				'99'
			)
		).toBe( 12 );
	} );

	it( 'specialEnding uses explicit base when provided', () => {
		expect(
			applyNumericOperation(
				'50.00',
				{ type: 'increase', amount: 10 },
				salePriceField,
				100,
				'99'
			)
		).toBe( '110.99' );
	} );
} );

describe( 'inferBulkOperationKind', () => {
	const table: { type: Field[ 'type' ]; expected: string | null }[] = [
		{ type: 'number', expected: 'numeric' },
		{ type: 'price', expected: 'numeric' },
		{ type: 'integer', expected: 'numeric' },
		{ type: 'text', expected: 'text' },
		{ type: 'textarea', expected: 'text' },
		{ type: 'select', expected: 'text' },
		{ type: 'boolean', expected: 'boolean' },
		{ type: 'taxonomy', expected: 'taxonomy' },
		{ type: 'image', expected: null },
		{ type: 'gallery', expected: null },
		{ type: 'date', expected: null },
		{ type: 'custom_meta', expected: null },
	];

	for ( const { type, expected } of table ) {
		it( `maps ${ type } → ${ expected ?? 'null' }`, () => {
			expect( inferBulkOperationKind( makeField( { type } ) ) ).toBe(
				expected
			);
		} );
	}
} );
