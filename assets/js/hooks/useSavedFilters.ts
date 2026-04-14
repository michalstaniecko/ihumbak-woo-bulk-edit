import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
	fetchSavedFilters,
	createSavedFilter,
	updateSavedFilter,
	deleteSavedFilter,
} from '@/api/savedFilters';
import { useRecentFiltersStore } from '@/store/useRecentFiltersStore';
import type {
	SavedFilter,
	CreateSavedFilterPayload,
	UpdateSavedFilterPayload,
} from '@/types/api';

const QUERY_KEY = [ 'savedFilters' ] as const;

export function useSavedFilters() {
	return useQuery( {
		queryKey: QUERY_KEY,
		queryFn: ( { signal } ) => fetchSavedFilters( signal ),
		staleTime: 60_000,
	} );
}

export function useCreateSavedFilter() {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: ( payload: CreateSavedFilterPayload ) =>
			createSavedFilter( payload ),
		onSuccess: () => {
			queryClient.invalidateQueries( { queryKey: QUERY_KEY } );
		},
	} );
}

export function useUpdateSavedFilter() {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: ( { id, payload }: { id: number; payload: UpdateSavedFilterPayload } ) =>
			updateSavedFilter( id, payload ),
		onSuccess: () => {
			queryClient.invalidateQueries( { queryKey: QUERY_KEY } );
		},
	} );
}

export function useDeleteSavedFilter() {
	const queryClient = useQueryClient();

	return useMutation( {
		mutationFn: ( id: number ) => deleteSavedFilter( id ),
		onSuccess: ( _data, id ) => {
			queryClient.invalidateQueries( { queryKey: QUERY_KEY } );
			useRecentFiltersStore.getState().removeById( id );
		},
	} );
}
