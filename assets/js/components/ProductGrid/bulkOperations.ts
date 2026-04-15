import type { Field, TaxonomyTerm } from '@/types/api';

/* ── Special ending rounding ───────────────────────────────────────────── */

export type SpecialEnding = 'none' | '00' | '90' | '99' | '9_00';

/**
 * Round a price UP to the nearest .00, .90, or .99 ending.
 * Integer-cents math is used throughout to avoid floating-point drift.
 *
 * .00  — if fractional part ≠ 0, advance to next integer .00; idempotent otherwise.
 * .90  — if frac < 90 → same integer .90;
 *         if frac > 90 → next integer .90;
 *         if frac == 90 → unchanged.
 * .99  — if frac == 99 → unchanged; otherwise → same integer .99.
 * none — passthrough, no change.
 */
export function applySpecialEnding(
	price: number,
	ending: SpecialEnding
): number {
	if ( ending === 'none' ) {
		return price;
	}

	// Work in integer cents to avoid floating-point drift.
	const cents = Math.round( price * 100 );
	const wholePart = Math.floor( cents / 100 ); // integer part
	const fracPart = cents % 100; // 0–99

	switch ( ending ) {
		case '00': {
			if ( fracPart === 0 ) {
				return wholePart; // already .00
			}
			return wholePart + 1; // round up to next .00
		}
		case '90': {
			if ( fracPart === 90 ) {
				return wholePart + 0.9; // already .90, unchanged
			}
			if ( fracPart < 90 ) {
				return wholePart + 0.9; // same integer .90
			}
			// fracPart > 90 → next integer .90
			return wholePart + 1 + 0.9;
		}
		case '99': {
			// fracPart === 99 is idempotent — same formula applies either way
			return wholePart + 0.99;
		}
		case '9_00': {
			// Round UP to nearest integer ending in 9 with zero cents (9, 19, 29, ..., 99, 109, ...).
			if ( wholePart % 10 === 9 && fracPart === 0 ) {
				return wholePart; // already X9.00 — idempotent
			}
			const effectiveWhole = wholePart + ( fracPart > 0 ? 1 : 0 );
			const lastDigit = effectiveWhole % 10;
			const diff = ( 9 - lastDigit + 10 ) % 10;
			return effectiveWhole + diff;
		}
	}
}

/* ── Operation type unions ─────────────────────────────────────────────── */

export type NumericOperation =
	| { type: 'set'; value: number }
	| { type: 'increase'; amount: number }
	| { type: 'decrease'; amount: number }
	| { type: 'increase_pct'; percent: number }
	| { type: 'decrease_pct'; percent: number }
	| { type: 'round'; precision: number }
	| { type: 'clear' };

export type TextCaseMode = 'upper' | 'lower' | 'title' | 'sentence';

export type TextOperation =
	| { type: 'set'; value: string }
	| {
			type: 'search_replace';
			search: string;
			replace: string;
			caseSensitive: boolean;
	  }
	| { type: 'append'; value: string }
	| { type: 'prepend'; value: string }
	| { type: 'case'; mode: TextCaseMode }
	| { type: 'clear' };

export type BooleanOperation =
	| { type: 'set_true' }
	| { type: 'set_false' }
	| { type: 'toggle' };

export type TaxonomyOperation =
	| { type: 'add'; terms: TaxonomyTerm[] }
	| { type: 'remove'; termIds: number[] }
	| { type: 'replace'; terms: TaxonomyTerm[] };

export type BulkOperation =
	| { kind: 'numeric'; op: NumericOperation }
	| { kind: 'text'; op: TextOperation }
	| { kind: 'boolean'; op: BooleanOperation }
	| { kind: 'taxonomy'; op: TaxonomyOperation };

/* ── Helpers ───────────────────────────────────────────────────────────── */

function toFiniteNumber( value: unknown ): number | null {
	if ( value === null || value === undefined || value === '' ) {
		return null;
	}
	const num =
		typeof value === 'number' ? value : parseFloat( String( value ) );
	return Number.isFinite( num ) ? num : null;
}

function formatNumberForField( num: number, field: Field ): number | string {
	if ( field.type === 'price' ) {
		// WooCommerce stores prices as strings with 2 decimals
		return num.toFixed( 2 );
	}
	if ( field.type === 'integer' ) {
		return Math.trunc( num );
	}
	return num;
}

function escapeRegExp( str: string ): string {
	return str.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

function titleCase( str: string ): string {
	return str.replace( /\w\S*/g, ( word ) => {
		return word.charAt( 0 ).toUpperCase() + word.slice( 1 ).toLowerCase();
	} );
}

function sentenceCase( str: string ): string {
	const lower = str.toLowerCase();
	return lower.replace( /(^\s*\w|[.!?]\s+\w)/g, ( c ) => c.toUpperCase() );
}

/* ── Per-kind appliers ─────────────────────────────────────────────────── */

export function applyNumericOperation(
	currentValue: unknown,
	op: NumericOperation,
	field: Field,
	baseValue?: unknown,
	specialEnding: SpecialEnding = 'none'
): number | string | null {
	if ( op.type === 'clear' ) {
		return field.type === 'price' ? '' : null;
	}

	if ( op.type === 'set' ) {
		const raw = op.value;
		const rounded =
			field.type === 'price' && specialEnding !== 'none'
				? applySpecialEnding( raw, specialEnding )
				: raw;
		return formatNumberForField( rounded, field );
	}

	const source = baseValue !== undefined ? baseValue : currentValue;
	const sourceNum = toFiniteNumber( source );
	const base = sourceNum ?? 0;

	let result: number;
	switch ( op.type ) {
		case 'increase':
			result = base + op.amount;
			break;
		case 'decrease':
			result = base - op.amount;
			break;
		case 'increase_pct':
			result = base * ( 1 + op.percent / 100 );
			break;
		case 'decrease_pct':
			result = base * ( 1 - op.percent / 100 );
			break;
		case 'round': {
			const factor = Math.pow( 10, op.precision );
			result = Math.round( base * factor ) / factor;
			break;
		}
	}

	const rounded =
		field.type === 'price' && specialEnding !== 'none'
			? applySpecialEnding( result, specialEnding )
			: result;

	return formatNumberForField( rounded, field );
}

export function applyTextOperation(
	currentValue: unknown,
	op: TextOperation
): string {
	const str =
		currentValue === null || currentValue === undefined
			? ''
			: String( currentValue );

	switch ( op.type ) {
		case 'set':
			return op.value;
		case 'clear':
			return '';
		case 'append':
			return str + op.value;
		case 'prepend':
			return op.value + str;
		case 'search_replace': {
			if ( op.search === '' ) {
				return str;
			}
			const flags = op.caseSensitive ? 'g' : 'gi';
			const regex = new RegExp( escapeRegExp( op.search ), flags );
			return str.replace( regex, op.replace );
		}
		case 'case':
			switch ( op.mode ) {
				case 'upper':
					return str.toUpperCase();
				case 'lower':
					return str.toLowerCase();
				case 'title':
					return titleCase( str );
				case 'sentence':
					return sentenceCase( str );
			}
	}
}

export function applyBooleanOperation(
	currentValue: unknown,
	op: BooleanOperation
): boolean {
	switch ( op.type ) {
		case 'set_true':
			return true;
		case 'set_false':
			return false;
		case 'toggle':
			return ! currentValue;
	}
}

function dedupeTerms( terms: TaxonomyTerm[] ): TaxonomyTerm[] {
	const seen = new Set< number >();
	const out: TaxonomyTerm[] = [];
	for ( const t of terms ) {
		if ( ! seen.has( t.id ) ) {
			seen.add( t.id );
			out.push( t );
		}
	}
	return out;
}

export function applyTaxonomyOperation(
	currentValue: unknown,
	op: TaxonomyOperation
): TaxonomyTerm[] {
	const current: TaxonomyTerm[] = Array.isArray( currentValue )
		? ( currentValue as TaxonomyTerm[] ).filter(
				( t ): t is TaxonomyTerm =>
					typeof t === 'object' &&
					t !== null &&
					typeof ( t as TaxonomyTerm ).id === 'number'
		  )
		: [];

	switch ( op.type ) {
		case 'add':
			return dedupeTerms( [ ...current, ...op.terms ] );
		case 'remove': {
			const removeSet = new Set( op.termIds );
			return current.filter( ( t ) => ! removeSet.has( t.id ) );
		}
		case 'replace':
			return dedupeTerms( op.terms );
	}
}

/* ── Category inference from field ─────────────────────────────────────── */

export type BulkOperationKind = 'numeric' | 'text' | 'boolean' | 'taxonomy';

export function inferBulkOperationKind(
	field: Field
): BulkOperationKind | null {
	switch ( field.type ) {
		case 'number':
		case 'price':
		case 'integer':
			return 'numeric';
		case 'text':
		case 'textarea':
			return 'text';
		case 'boolean':
			return 'boolean';
		case 'taxonomy':
			return 'taxonomy';
		case 'select':
			// Select acts like text via Set value
			return 'text';
		default:
			return null;
	}
}

/* ── Top-level dispatcher ──────────────────────────────────────────────── */

export function applyBulkOperation(
	currentValue: unknown,
	operation: BulkOperation,
	field: Field
): unknown {
	switch ( operation.kind ) {
		case 'numeric':
			return applyNumericOperation( currentValue, operation.op, field );
		case 'text':
			return applyTextOperation( currentValue, operation.op );
		case 'boolean':
			return applyBooleanOperation( currentValue, operation.op );
		case 'taxonomy':
			return applyTaxonomyOperation( currentValue, operation.op );
	}
}
