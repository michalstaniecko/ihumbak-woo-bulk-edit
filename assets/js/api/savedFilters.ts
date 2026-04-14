import { apiFetch } from './client';
import {
	SavedFilterSchema,
	SavedFiltersListResponseSchema,
	DeleteSavedFilterResponseSchema,
} from '@/types/api';
import type {
	SavedFilter,
	SavedFiltersListResponse,
	CreateSavedFilterPayload,
	UpdateSavedFilterPayload,
	DeleteSavedFilterResponse,
} from '@/types/api';

export function fetchSavedFilters( signal?: AbortSignal ): Promise< SavedFiltersListResponse > {
	return apiFetch( 'filters', SavedFiltersListResponseSchema, { signal } );
}

export function createSavedFilter(
	payload: CreateSavedFilterPayload,
	signal?: AbortSignal
): Promise< SavedFilter > {
	return apiFetch( 'filters', SavedFilterSchema, {
		method: 'POST',
		body: payload,
		signal,
	} );
}

export function updateSavedFilter(
	id: number,
	payload: UpdateSavedFilterPayload,
	signal?: AbortSignal
): Promise< SavedFilter > {
	return apiFetch( `filters/${ id }`, SavedFilterSchema, {
		method: 'PUT',
		body: payload,
		signal,
	} );
}

export function deleteSavedFilter(
	id: number,
	signal?: AbortSignal
): Promise< DeleteSavedFilterResponse > {
	return apiFetch( `filters/${ id }`, DeleteSavedFilterResponseSchema, {
		method: 'DELETE',
		signal,
	} );
}
