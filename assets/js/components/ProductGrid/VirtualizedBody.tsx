import { useCallback, useEffect } from 'react';
import { List } from 'react-window';
import type { Row, Table } from '@tanstack/react-table';
import type { Product } from '@/types/api';
import { useEditingStore } from '@/store';
import { GridRow } from './GridRow';

const ROW_HEIGHT = 40;
const MAX_VISIBLE_HEIGHT = 600;

interface VirtualizedBodyProps {
	table: Table< Product >;
	columnWidths: number[];
	page?: number;
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
}: VirtualizedBodyProps ): JSX.Element {
	const rows = table.getRowModel().rows;
	const itemCount = rows.length;
	const stopEditing = useEditingStore( ( s ) => s.stopEditing );

	// Clear editing state when page changes
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

	if ( itemCount === 0 ) {
		return <div className="iwbe-grid-empty">{ '\u2014' }</div>;
	}

	const RowRenderer = ( { index, style }: VirtualizedRowProps ) => {
		const row = rows[ index ];
		return (
			<GridRow
				row={ row }
				style={ style }
				onRowClick={ onRowClick }
				columnWidths={ columnWidths }
			/>
		);
	};

	return (
		<List
			rowComponent={ RowRenderer }
			rowCount={ itemCount }
			rowHeight={ ROW_HEIGHT }
			rowProps={ {} }
			style={ { maxHeight: MAX_VISIBLE_HEIGHT } }
		/>
	);
}
