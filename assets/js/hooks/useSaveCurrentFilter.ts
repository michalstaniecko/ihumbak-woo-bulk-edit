import { useCallback } from 'react';
import { useCreateSavedFilter } from '@/hooks/useSavedFilters';
import { useRecentFiltersStore } from '@/store/useRecentFiltersStore';
import { selectIsComplex, selectLegacyFilters } from '@/store/useFiltersStore';
import type {
	FilterGroup,
	SavedFilter,
	SavedFilterDefinition,
	CreateSavedFilterPayload,
} from '@/types/api';

interface SaveArgs {
	name: string;
	isShared: boolean;
	root: FilterGroup;
	search: string;
}

interface UseSaveCurrentFilterResult {
	save: ( args: SaveArgs ) => Promise< SavedFilter >;
	isSaving: boolean;
}

/**
 * Recursively strip empty group nodes from a FilterGroup tree.
 * An empty group (no children) is semantically a no-op — the user most likely
 * clicked "+ Add group" by accident. Stripping them before save prevents the
 * backend from returning wbm_filter_empty on an otherwise valid filter tree.
 */
export function stripEmptyGroups( root: FilterGroup ): FilterGroup {
	const cleanedChildren = root.children
		.map( ( child ) => {
			if ( child.type === 'group' ) {
				return stripEmptyGroups( child as FilterGroup );
			}
			return child;
		} )
		.filter( ( child ) => {
			if ( child.type === 'group' ) {
				return ( child as FilterGroup ).children.length > 0;
			}
			return true;
		} );
	return { ...root, children: cleanedChildren };
}

/**
 * Returns true when the root, after stripping empty groups, has no conditions
 * or non-empty groups. Used to disable the "Save" button.
 */
export function isEffectivelyEmpty( root: FilterGroup ): boolean {
	return stripEmptyGroups( root ).children.length === 0;
}

/**
 * Build the `SavedFilterDefinition` from the current tree root and search
 * query. This is a pure function — no React, no side effects — so it can be
 * unit-tested in isolation.
 *
 * Serialization strategy:
 *   - Empty groups are stripped from the tree before serializing (no-ops).
 *   - Flat AND-only root (after stripping) with no nested groups →
 *     `filters` as `ProductFilter[]` (legacy shape, back-compat with old presets).
 *   - OR root or root with nested groups → `filters` as the full `FilterGroup`
 *     tree, so no structural information is lost on save.
 */
export function buildSavedFilterDefinition(
	root: FilterGroup,
	search: string
): SavedFilterDefinition {
	const cleanRoot = stripEmptyGroups( root );
	return {
		filters: selectIsComplex( cleanRoot ) ? cleanRoot : selectLegacyFilters( cleanRoot ),
		search,
	};
}

/**
 * Hook that wires `buildSavedFilterDefinition` to the create-saved-filter
 * mutation and the recent-filters store.
 *
 * Error handling is intentionally left to the caller — any mutation error is
 * re-thrown so callers can catch and display it themselves.
 */
export function useSaveCurrentFilter(): UseSaveCurrentFilterResult {
	const createMutation = useCreateSavedFilter();
	const { touch } = useRecentFiltersStore();

	const save = useCallback(
		async ( { name, isShared, root, search }: SaveArgs ): Promise< SavedFilter > => {
			const payload: CreateSavedFilterPayload = {
				name,
				is_shared: isShared,
				definition: buildSavedFilterDefinition( root, search ),
			};

			const created = await createMutation.mutateAsync( payload );

			touch( {
				id: created.id,
				name: created.name,
				definition: created.definition,
				usedAt: Date.now(),
			} );

			return created;
		},
		[ createMutation, touch ]
	);

	return {
		save,
		isSaving: createMutation.isPending,
	};
}
