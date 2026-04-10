import { __ } from '@wordpress/i18n';

export function App(): JSX.Element {
	return (
		<div className="iwbe-app">
			<h1>{ __( 'Bulk Edit Products', 'ihumbak-woo-bulk-edit' ) }</h1>
		</div>
	);
}
