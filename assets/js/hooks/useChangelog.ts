import { useQuery } from '@tanstack/react-query';
import type { UseQueryResult } from '@tanstack/react-query';
import { fetchChangelog } from '@/api/changelog';
import type { ChangelogResponse, ChangelogQueryParams } from '@/types/api';

export function useChangelog(
	params: ChangelogQueryParams,
	enabled = true
): UseQueryResult< ChangelogResponse > {
	return useQuery( {
		queryKey: [ 'changelog', params ],
		queryFn: ( { signal } ) => fetchChangelog( params, signal ),
		enabled,
		staleTime: 30 * 1000,
	} );
}
