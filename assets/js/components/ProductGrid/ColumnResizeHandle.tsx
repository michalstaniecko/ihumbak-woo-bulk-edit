import type { Header } from '@tanstack/react-table';
import type { Product } from '@/types/api';
import { useColumnLayoutStore } from '@/store';

interface ColumnResizeHandleProps {
	header: Header< Product, unknown >;
}

/**
 * Drag handle rendered on the right edge of a resizable header cell.
 * Uses TanStack Table's built-in `header.getResizeHandler()`.
 * Double-click resets the column to its default size.
 */
export function ColumnResizeHandle( {
	header,
}: ColumnResizeHandleProps ): JSX.Element {
	const columnSizing = useColumnLayoutStore( ( s ) => s.columnSizing );
	const setColumnSizing = useColumnLayoutStore( ( s ) => s.setColumnSizing );

	const handleDoubleClick = () => {
		// Remove custom width entry to fall back to the column's default size.
		const next = { ...columnSizing };
		delete next[ header.column.id ];
		setColumnSizing( next );
	};

	const resizeHandler = header.getResizeHandler();

	const handleMouseDown = ( e: React.MouseEvent ) => {
		// Calling preventDefault() on mousedown tells the browser not to start
		// an HTML drag gesture, even though the parent header cell is draggable.
		e.preventDefault();
		resizeHandler( e );
	};

	return (
		<div
			className={ `iwbe-resize-handle${ header.column.getIsResizing() ? ' iwbe-resize-handle--active' : '' }` }
			onMouseDown={ handleMouseDown }
			onTouchStart={ resizeHandler as React.TouchEventHandler }
			onDoubleClick={ handleDoubleClick }
			role="separator"
			aria-hidden="true"
		/>
	);
}
