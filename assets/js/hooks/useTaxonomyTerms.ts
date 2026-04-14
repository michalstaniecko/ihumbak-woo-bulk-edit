import { useQuery, useQueries } from '@tanstack/react-query';
import { fetchTaxonomyTerms } from '@/api/taxonomyTerms';
import type { Field, ProductFilter, TaxonomyTermsResponse } from '@/types/api';

const STALE_TIME = 5 * 60 * 1000; // 5 minutes
const GC_TIME = 5 * 60 * 1000; // 5 minutes

/**
 * Fetch taxonomy terms for a field with optional search string.
 *
 * Returns the React Query result directly so the component can read
 * isLoading, isError, data, etc.
 */
export function useTaxonomyTerms(
	fieldKey: string,
	search: string = '',
	enabled: boolean = true
) {
	return useQuery( {
		queryKey: [ 'taxonomyTerms', fieldKey, search ],
		queryFn: ( { signal } ) =>
			fetchTaxonomyTerms( { fieldKey, search: search || undefined }, signal ),
		staleTime: STALE_TIME,
		gcTime: GC_TIME,
		enabled,
	} );
}

/**
 * Operators that use term_id (numeric) values and need label resolution.
 */
const TERM_ID_OPERATORS = new Set( [ '=', '!=' ] );

/**
 * Build a map of "${ fieldKey }:${ termId }" → term name for all taxonomy
 * filters that use = / != with a numeric ID value.
 *
 * Makes ONE `?include=...` request per taxonomy field key that has ID-based
 * filters, so that the filter chips can display human-readable names instead
 * of raw IDs.
 */
export function useTaxonomyTermLabels(
	filters: ProductFilter[],
	fields: Field[]
): Record< string, string > {
	// Group numeric term IDs by field key.
	const grouped: Record< string, Set< number > > = {};

	for ( const filter of filters ) {
		if ( ! TERM_ID_OPERATORS.has( filter.operator ) ) continue;

		const value = filter.value;
		if ( ! value || ! /^\d+$/.test( value ) ) continue;

		const field = fields.find( ( f ) => f.key === filter.field );
		if ( ! field || field.type !== 'taxonomy' ) continue;

		if ( ! grouped[ filter.field ] ) {
			grouped[ filter.field ] = new Set();
		}
		grouped[ filter.field ].add( Number( value ) );
	}

	const fieldKeys = Object.keys( grouped );

	// One query per taxonomy field key (include=id1,id2,...).
	const queries = useQueries( {
		queries: fieldKeys.map( ( fieldKey ) => ( {
			queryKey: [
				'taxonomyTermLabels',
				fieldKey,
				[ ...grouped[ fieldKey ] ].sort().join( ',' ),
			],
			queryFn: ( { signal }: { signal?: AbortSignal } ) =>
				fetchTaxonomyTerms(
					{
						fieldKey,
						include: [ ...( grouped[ fieldKey ] ?? [] ) ],
					},
					signal
				),
			staleTime: STALE_TIME,
			gcTime: GC_TIME,
			enabled: ( grouped[ fieldKey ]?.size ?? 0 ) > 0,
		} ) ),
	} );

	// Build the label map from all resolved queries.
	const labelMap: Record< string, string > = {};

	fieldKeys.forEach( ( fieldKey, idx ) => {
		const result = queries[ idx ];
		const data = result?.data as TaxonomyTermsResponse | undefined;
		if ( ! data ) return;

		for ( const term of data.items ) {
			labelMap[ `${ fieldKey }:${ term.id }` ] = term.name;
		}
	} );

	return labelMap;
}
