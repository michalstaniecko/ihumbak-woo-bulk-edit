import { useCallback, useEffect, useMemo } from 'react';
import { List } from 'react-window';
import type { Row, Table } from '@tanstack/react-table';
import type { Product, Field, Variation, VariationsResponse } from '@/types/api';
import { useEditingStore } from '@/store';
import { GridRow } from './GridRow';
import { VariationRow } from './VariationRow';
import type { DisplayRow } from './displayRows';
import { buildDisplayRows } from './displayRows';

const ROW_HEIGHT = 40;
const VARIATION_ROW_HEIGHT = 38;
const MAX_VISIBLE_HEIGHT = 600;

interface VirtualizedBodyProps {
	table: Table< Product >;
	columnWidths: number[];
	page?: number;
	fields: Field[];
	expandedSet: Set< number >;
	variationsMap: Map< number, VariationsResponse >;
}

interface VirtualizedRowProps {
	index: number;
	style: React.CSSProperties;
	ariaAttributes: Record< string, unknown >;
}

export function VirtualizedBody( {
	table,
	columnWidths,
	page,
	fields,
	expandedSet,
	variationsMap,
}: VirtualizedBodyProps ): JSX.Element {
	const rows = table.getRowModel().rows;
	const stopEditing = useEditingStore( ( s ) => s.stopEditing );

	// Clear editing state when page changes.
	useEffect( () => {
		stopEditing();
	}, [ page, stopEditing ] );

	const handleShiftClick = ( table.options.meta as {
		handleShiftClick?: (
			row: Row< Product >,
			event: React.MouseEvent
		) => void;
	} )?.handleShiftClick;

	const onRowClick = useCallback(
		( row: Row< Product >, event: React.MouseEvent ) => {
			if ( handleShiftClick ) {
				handleShiftClick( row, event );
			} else {
				row.toggleSelected();
			}
		},
		[ handleShiftClick ]
	);

	// Convert VariationsResponse map → Variation[] map for buildDisplayRows.
	const variationItemsMap = useMemo( (): Map< number, Variation[] > => {
		const m = new Map< number, Variation[] >();
		for ( const [ id, resp ] of variationsMap ) {
			m.set( id, resp.items );
		}
		return m;
	}, [ variationsMap ] );

	// Build the flat list of display rows (parents + injected variation rows).
	const displayRows: DisplayRow[] = buildDisplayRows(
		rows,
		expandedSet,
		variationItemsMap
	);

	const itemCount = displayRows.length;

	if ( rows.length === 0 ) {
		return <div className="iwbe-grid-empty">{ '\u2014' }</div>;
	}

	const rowHeight = ( index: number ): number => {
		const dr = displayRows[ index ];
		return dr?.kind === 'variation' ? VARIATION_ROW_HEIGHT : ROW_HEIGHT;
	};

	const RowRenderer = ( { index, style }: VirtualizedRowProps ) => {
		const dr = displayRows[ index ];

		if ( ! dr ) {
			return null;
		}

		if ( dr.kind === 'variation' ) {
			return (
				<VariationRow
					variation={ dr.variation }
					fields={ fields }
					style={ style }
					columnWidths={ columnWidths }
				/>
			);
		}

		return (
			<GridRow
				row={ dr.row }
				style={ style }
				onRowClick={ onRowClick }
				columnWidths={ columnWidths }
				isExpanded={ dr.isExpanded }
				isLoadingVariations={ dr.isLoadingVariations }
			/>
		);
	};

	return (
		<List
			rowComponent={ RowRenderer }
			rowCount={ itemCount }
			rowHeight={ rowHeight }
			rowProps={ {} }
			style={ { maxHeight: MAX_VISIBLE_HEIGHT } }
		/>
	);
}
