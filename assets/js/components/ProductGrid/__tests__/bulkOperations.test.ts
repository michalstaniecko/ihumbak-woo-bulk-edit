import { describe, it, expect } from 'vitest';
import {
	applyNumericOperation,
	applyTextOperation,
	applyBooleanOperation,
	applyTaxonomyOperation,
	inferBulkOperationKind,
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
