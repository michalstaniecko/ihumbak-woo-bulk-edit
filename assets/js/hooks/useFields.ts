import { useQuery } from '@tanstack/react-query';
import type { UseQueryResult } from '@tanstack/react-query';
import { fetchFields } from '@/api/fields';
import type { FieldsResponse } from '@/types/api';

const FIELDS_QUERY_KEY = [ 'fields' ] as const;

export function useFields(): UseQueryResult< FieldsResponse > {
	return useQuery( {
		queryKey: FIELDS_QUERY_KEY,
		queryFn: ( { signal } ) => fetchFields( signal ),
		staleTime: 10 * 60 * 1000,
	} );
}
