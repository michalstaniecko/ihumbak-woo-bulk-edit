import { __ } from '@wordpress/i18n';

interface ExpansionToggleProps {
	isExpanded: boolean;
	isLoading: boolean;
	variationsCount: number;
	onToggle: () => void;
}

/**
 * Expand / collapse toggle button rendered in the first cell of a variable
 * product row. Clicking it triggers lazy-loading of the product's variations.
 */
export function ExpansionToggle( {
	isExpanded,
	isLoading,
	variationsCount,
	onToggle,
}: ExpansionToggleProps ): JSX.Element {
	const label = isExpanded
		? __( 'Collapse variations', 'ihumbak-woo-bulk-edit' )
		: __( 'Expand variations', 'ihumbak-woo-bulk-edit' );

	return (
		<button
			type="button"
			className={ `iwbe-expansion-toggle${ isExpanded ? ' iwbe-expansion-toggle--open' : '' }` }
			aria-label={ label }
			aria-expanded={ isExpanded }
			onClick={ ( e ) => {
				e.stopPropagation();
				onToggle();
			} }
			title={ label }
		>
			{ isLoading ? (
				<span className="iwbe-expansion-toggle__spinner" aria-hidden="true" />
			) : (
				<span
					className="iwbe-expansion-toggle__icon"
					aria-hidden="true"
				>
					{ isExpanded ? '▼' : '▶' }
				</span>
			) }
			<span className="iwbe-expansion-toggle__count">
				{ variationsCount }
			</span>
		</button>
	);
}
