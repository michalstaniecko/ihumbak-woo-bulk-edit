import { __ } from '@wordpress/i18n';
import { ProductGrid } from './ProductGrid';
import { useUndoRedoShortcuts } from '@/hooks/useUndoRedoShortcuts';
import '../../css/product-grid.css';

export function App(): JSX.Element {
	useUndoRedoShortcuts();

	return (
		<div className="iwbe-app">
			<h1>{ __( 'Bulk Edit Products', 'ihumbak-woo-bulk-edit' ) }</h1>
			<ProductGrid />
		</div>
	);
}
