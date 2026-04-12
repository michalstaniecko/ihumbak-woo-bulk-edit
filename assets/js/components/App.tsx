import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ProductGrid } from './ProductGrid';
import { ChangeHistoryPanel } from './ChangeHistoryPanel';
import { useUndoRedoShortcuts } from '@/hooks/useUndoRedoShortcuts';
import '../../css/product-grid.css';

export function App(): JSX.Element {
	useUndoRedoShortcuts();

	const [ historyOpen, setHistoryOpen ] = useState( false );

	return (
		<div className="iwbe-app">
			<div className="iwbe-app-header">
				<h1>{ __( 'Bulk Edit Products', 'ihumbak-woo-bulk-edit' ) }</h1>
				<button
					type="button"
					className="button iwbe-history-toggle"
					onClick={ () => setHistoryOpen( true ) }
				>
					{ __( 'Change History', 'ihumbak-woo-bulk-edit' ) }
				</button>
			</div>
			<ProductGrid />
			<ChangeHistoryPanel
				open={ historyOpen }
				onClose={ () => setHistoryOpen( false ) }
			/>
		</div>
	);
}
