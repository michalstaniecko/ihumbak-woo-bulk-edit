import { apiFetch } from './client';
import { ColumnVisibilityPreferenceSchema } from '@/types/api';
import type { ColumnVisibilityPreference } from '@/types/api';

const ENDPOINT = 'preferences/column-visibility';

/**
 * Fetch the current user's column visibility preferences.
 */
export function fetchColumnVisibility(
	signal?: AbortSignal
): Promise< ColumnVisibilityPreference > {
	return apiFetch( ENDPOINT, ColumnVisibilityPreferenceSchema, { signal } );
}

/**
 * Update the hidden columns list for the current user.
 */
export function updateColumnVisibility(
	hidden: string[],
	signal?: AbortSignal
): Promise< ColumnVisibilityPreference > {
	return apiFetch( ENDPOINT, ColumnVisibilityPreferenceSchema, {
		method: 'PUT',
		body: { hidden },
		signal,
	} );
}

/**
 * Reset the current user's column visibility to defaults.
 */
export function resetColumnVisibility(
	signal?: AbortSignal
): Promise< ColumnVisibilityPreference > {
	return apiFetch( ENDPOINT, ColumnVisibilityPreferenceSchema, {
		method: 'DELETE',
		signal,
	} );
}
