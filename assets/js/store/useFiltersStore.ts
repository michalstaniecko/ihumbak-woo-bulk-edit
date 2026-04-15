import { create } from 'zustand';
import type { ProductFilter, SavedFilterDefinition, FilterGroup, FilterNode, FilterCondition } from '@/types/api';
import { legacyFiltersToGroup } from '@/types/api';

// ── Path utilities ────────────────────────────────────────────────────────────

/**
 * Parse a dot-separated path string into an array of numeric indices.
 * Empty string means "root" (returns []).
 */
function parsePath( path: string ): number[] {
	if ( path === '' ) return [];
	return path.split( '.' ).map( ( p ) => parseInt( p, 10 ) );
}

/**
 * Immutably update the children of the group at `path` using an updater function.
 * Returns the updated root. If the path is invalid, returns root unchanged.
 */
function updateGroupChildren(
	root: FilterGroup,
	path: number[],
	updater: ( children: FilterNode[] ) => FilterNode[]
): FilterGroup {
	if ( path.length === 0 ) {
		return { ...root, children: updater( root.children ) };
	}

	const [ head, ...rest ] = path;
	const newChildren: FilterNode[] = root.children.map( ( node: FilterNode, idx: number ) => {
		if ( idx !== head ) return node;
		if ( node.type !== 'group' ) return node;
		return updateGroupChildren( node as FilterGroup, rest, updater );
	} );

	return { ...root, children: newChildren };
}

/**
 * Immutably update the node at `path` with an updater function.
 */
function updateNodeAtPath(
	root: FilterGroup,
	path: number[],
	updater: ( node: FilterNode ) => FilterNode
): FilterGroup {
	if ( path.length === 0 ) {
		return updater( root ) as FilterGroup;
	}

	const parentPath = path.slice( 0, -1 );
	const leafIdx = path[ path.length - 1 ];

	return updateGroupChildren( root, parentPath, ( children: FilterNode[] ) =>
		children.map( ( node: FilterNode, idx: number ) => ( idx === leafIdx ? updater( node ) : node ) )
	);
}

// ── selectLegacyFilters ───────────────────────────────────────────────────────

/**
 * Extract top-level conditions from a FilterGroup as a flat ProductFilter array.
 * Returns [] for OR roots (complex tree).
 * For AND roots, returns only the direct condition children — nested groups
 * are silently excluded (they can only be represented in the builder UI, not as chips).
 */
export function selectLegacyFilters( root: FilterGroup ): ProductFilter[] {
	if ( root.combinator !== 'AND' ) return [];

	const result: ProductFilter[] = [];
	for ( const child of root.children ) {
		if ( child.type !== 'condition' ) continue; // skip nested groups
		const cond = child as FilterCondition;
		result.push( {
			field: cond.field,
			operator: cond.operator as ProductFilter[ 'operator' ],
			value: Array.isArray( cond.value ) ? cond.value.join( ',' ) : cond.value,
		} );
	}
	return result;
}

// Re-export legacyFiltersToGroup helper from api.ts for convenience.
export { legacyFiltersToGroup };

// ── Store ─────────────────────────────────────────────────────────────────────

const EMPTY_ROOT: FilterGroup = { type: 'group', combinator: 'AND', children: [] };

interface FiltersState {
	root: FilterGroup;
	searchQuery: string;

	setRoot: ( root: FilterGroup ) => void;
	addCondition: ( groupPath: string, condition: FilterCondition ) => void;
	addGroup: ( groupPath: string, combinator: 'AND' | 'OR' ) => void;
	removeNode: ( path: string ) => void;
	updateCondition: ( path: string, patch: Partial< FilterCondition > ) => void;
	setCombinator: ( groupPath: string, combinator: 'AND' | 'OR' ) => void;
	clearAll: () => void;
	setSearchQuery: ( q: string ) => void;
	applyPreset: ( definition: SavedFilterDefinition ) => void;

	// Legacy helpers kept for backward compatibility with FilterToolbar / SavedFiltersMenu.
	/** @deprecated Use setRoot instead. */
	setFilters: ( filters: ProductFilter[] ) => void;
	/** @deprecated Use addCondition instead. */
	addFilter: ( filter: ProductFilter ) => void;
	/** @deprecated Use removeNode instead. */
	removeFilter: ( index: number ) => void;
}

export const useFiltersStore = create< FiltersState >()( ( set, get ) => ( {
	root: { ...EMPTY_ROOT },
	searchQuery: '',

	// ── New tree API ────────────────────────────────────────────

	setRoot: ( root ) => set( { root } ),

	addCondition: ( groupPath, condition ) =>
		set( ( state ) => ( {
			root: updateGroupChildren( state.root, parsePath( groupPath ), ( children ) => [
				...children,
				condition,
			] ),
		} ) ),

	addGroup: ( groupPath, combinator ) =>
		set( ( state ) => ( {
			root: updateGroupChildren( state.root, parsePath( groupPath ), ( children ) => {
				const newGroup: FilterGroup = { type: 'group', combinator, children: [] };
				return [ ...children, newGroup ];
			} ),
		} ) ),

	removeNode: ( path ) => {
		const indices = parsePath( path );
		if ( indices.length === 0 ) return; // Can't remove root.

		const parentPath = indices.slice( 0, -1 );
		const leafIdx = indices[ indices.length - 1 ];

		set( ( state ) => ( {
			root: updateGroupChildren( state.root, parentPath, ( children ) =>
				children.filter( ( _, i ) => i !== leafIdx )
			),
		} ) );
	},

	updateCondition: ( path, patch ) => {
		const indices = parsePath( path );
		set( ( state ) => ( {
			root: updateNodeAtPath( state.root, indices, ( node ) =>
				( { ...node, ...patch } ) as FilterCondition
			),
		} ) );
	},

	setCombinator: ( groupPath, combinator ) => {
		const indices = parsePath( groupPath );
		if ( indices.length === 0 ) {
			set( ( state ) => ( { root: { ...state.root, combinator } } ) );
		} else {
			set( ( state ) => ( {
				root: updateNodeAtPath( state.root, indices, ( node ) => ( {
					...node,
					combinator,
				} ) ),
			} ) );
		}
	},

	clearAll: () =>
		set( {
			root: { ...EMPTY_ROOT, children: [] },
			searchQuery: '',
		} ),

	setSearchQuery: ( searchQuery ) => set( { searchQuery } ),

	applyPreset: ( definition ) => {
		const filtersRaw = definition.filters;
		let newRoot: FilterGroup;

		if ( Array.isArray( filtersRaw ) ) {
			// Legacy flat list.
			newRoot = legacyFiltersToGroup( filtersRaw as ProductFilter[] );
		} else {
			// Already a group tree.
			newRoot = filtersRaw as FilterGroup;
		}

		set( {
			root: newRoot,
			searchQuery: definition.search ?? '',
		} );
	},

	// ── Legacy API (backward compat) ────────────────────────────

	setFilters: ( filters ) => {
		set( { root: legacyFiltersToGroup( filters ) } );
	},

	addFilter: ( filter ) => {
		const condition: FilterCondition = {
			type: 'condition',
			field: filter.field,
			operator: filter.operator,
			value: filter.value,
		};
		set( ( state ) => ( {
			root: updateGroupChildren( state.root, [], ( children ) => [
				...children,
				condition,
			] ),
		} ) );
	},

	removeFilter: ( index ) => {
		set( ( state ) => ( {
			root: updateGroupChildren( state.root, [], ( children ) =>
				children.filter( ( _, i ) => i !== index )
			),
		} ) );
	},
} ) );
