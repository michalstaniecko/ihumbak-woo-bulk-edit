import type { Row } from '@tanstack/react-table';
import type { Product, Variation } from '@/types/api';

// ── Display row discriminated union ──────────────────────────────────────────

export type ParentDisplayRow = {
	kind: 'parent';
	row: Row< Product >;
	isExpanded: boolean;
	isLoadingVariations: boolean;
};

export type VariationDisplayRow = {
	kind: 'variation';
	parentId: number;
	variation: Variation;
};

export type DisplayRow = ParentDisplayRow | VariationDisplayRow;

// ── Pure computation function ─────────────────────────────────────────────────

/**
 * Flatten TanStack table rows + expanded variation rows into a single list
 * suitable for rendering in the virtualized body.
 *
 * This is extracted as a pure function so it can be unit-tested without
 * mounting any React components.
 *
 * @param rows            TanStack table rows from `table.getRowModel().rows`
 * @param expandedSet     Set of parent product IDs that are currently expanded
 * @param variationsMap   Map of parentId → Variation[] (items from VariationsResponse)
 */
export function buildDisplayRows(
	rows: Row< Product >[],
	expandedSet: Set< number >,
	variationsMap: Map< number, Variation[] >
): DisplayRow[] {
	const display: DisplayRow[] = [];

	for ( const row of rows ) {
		const productId = row.original.id;
		const isVariable = row.original.type === 'variable';
		const isExpanded = expandedSet.has( productId );

		// The variations data for this parent (may be undefined while loading).
		const variationsData = variationsMap.get( productId );

		// A parent is "loading" when it is expanded but we don't have its
		// variations data yet (React Query is still fetching).
		const isLoadingVariations =
			isExpanded && variationsData === undefined;

		display.push( {
			kind: 'parent',
			row,
			isExpanded,
			isLoadingVariations,
		} );

		if ( isExpanded && isVariable && variationsData ) {
			for ( const variation of variationsData ) {
				display.push( {
					kind: 'variation',
					parentId: productId,
					variation,
				} );
			}
		}
	}

	return display;
}
