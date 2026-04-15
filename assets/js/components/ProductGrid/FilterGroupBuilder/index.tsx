import { __ } from '@wordpress/i18n';
import type { Field, FilterGroup, FilterCondition, FilterNode } from '@/types/api';
import { FilterConditionRow } from './FilterConditionRow';

// ── Props ─────────────────────────────────────────────────────────────────────

export interface FilterGroupBuilderProps {
	group: FilterGroup;
	path: string;
	depth: number;
	maxDepth: number;
	fields: Field[];
	onAddCondition: ( path: string ) => void;
	onAddGroup: ( path: string ) => void;
	onRemoveNode: ( path: string ) => void;
	onUpdateCondition: ( path: string, patch: Partial< FilterCondition > ) => void;
	onSetCombinator: ( path: string, combinator: 'AND' | 'OR' ) => void;
}

// ── Component ─────────────────────────────────────────────────────────────────

export function FilterGroupBuilder( {
	group,
	path,
	depth,
	maxDepth,
	fields,
	onAddCondition,
	onAddGroup,
	onRemoveNode,
	onUpdateCondition,
	onSetCombinator,
}: FilterGroupBuilderProps ): JSX.Element {
	const isRoot = depth === 0;
	const atMaxDepth = depth >= maxDepth;

	const buildChildPath = ( index: number ): string =>
		path === '' ? String( index ) : `${ path }.${ index }`;

	return (
		<div
			className={ `iwbe-filter-group iwbe-filter-group--depth-${ depth }` }
			data-testid="filter-group"
		>
			{ /* Group header: combinator toggle + remove button */ }
			<div className="iwbe-filter-group-header">
				{ /* AND/OR toggle */ }
				<span className="iwbe-combinator-label">
					{ __( 'Match', 'ihumbak-woo-bulk-edit' ) }
				</span>
				<button
					type="button"
					className={ `iwbe-combinator-btn ${ group.combinator === 'AND' ? 'iwbe-combinator-btn--active' : '' }` }
					onClick={ () => onSetCombinator( path, 'AND' ) }
					aria-pressed={ group.combinator === 'AND' }
				>
					AND
				</button>
				<button
					type="button"
					className={ `iwbe-combinator-btn ${ group.combinator === 'OR' ? 'iwbe-combinator-btn--active' : '' }` }
					onClick={ () => onSetCombinator( path, 'OR' ) }
					aria-pressed={ group.combinator === 'OR' }
				>
					OR
				</button>

				{ /* Remove button (hidden for root) */ }
				{ ! isRoot && (
					<button
						type="button"
						className="iwbe-filter-group-remove-btn"
						onClick={ () => onRemoveNode( path ) }
						aria-label={ __( 'Remove group', 'ihumbak-woo-bulk-edit' ) }
					>
						{ __( 'Remove group', 'ihumbak-woo-bulk-edit' ) }
					</button>
				) }
			</div>

			{ /* Children */ }
			<div className="iwbe-filter-group-children">
				{ group.children.map( ( child: FilterNode, index ) => {
					const childPath = buildChildPath( index );

					if ( child.type === 'condition' ) {
						return (
							<FilterConditionRow
								key={ childPath }
								condition={ child as FilterCondition }
								path={ childPath }
								fields={ fields }
								onUpdate={ onUpdateCondition }
								onRemove={ onRemoveNode }
							/>
						);
					}

					// Nested group.
					return (
						<FilterGroupBuilder
							key={ childPath }
							group={ child as FilterGroup }
							path={ childPath }
							depth={ depth + 1 }
							maxDepth={ maxDepth }
							fields={ fields }
							onAddCondition={ onAddCondition }
							onAddGroup={ onAddGroup }
							onRemoveNode={ onRemoveNode }
							onUpdateCondition={ onUpdateCondition }
							onSetCombinator={ onSetCombinator }
						/>
					);
				} ) }
			</div>

			{ /* Action buttons */ }
			<div className="iwbe-filter-group-actions">
				<button
					type="button"
					className="iwbe-filter-add-condition-btn"
					onClick={ () => onAddCondition( path ) }
				>
					{ __( '+ Add condition', 'ihumbak-woo-bulk-edit' ) }
				</button>

				<button
					type="button"
					className="iwbe-filter-add-group-btn"
					onClick={ () => onAddGroup( path ) }
					disabled={ atMaxDepth }
					title={
						atMaxDepth
							? __( 'Maximum nesting depth reached', 'ihumbak-woo-bulk-edit' )
							: undefined
					}
				>
					{ __( '+ Add group', 'ihumbak-woo-bulk-edit' ) }
				</button>
			</div>
		</div>
	);
}
