import { apiFetch } from './client';
import { TaxonomyTermsResponseSchema } from '@/types/api';
import type { TaxonomyTermsResponse } from '@/types/api';

export interface FetchTaxonomyTermsParams {
	fieldKey: string;
	search?: string;
	perPage?: number;
	include?: number[];
}

/**
 * Fetch taxonomy terms for a given field key from the REST API.
 *
 * GET /taxonomies/{fieldKey}/terms
 *
 * Query params:
 *   search   — filter terms by name substring
 *   per_page — limit results (1–100, default 30)
 *   include  — CSV of term IDs to fetch directly (bypasses search/per_page)
 */
export function fetchTaxonomyTerms(
	params: FetchTaxonomyTermsParams,
	signal?: AbortSignal
): Promise< TaxonomyTermsResponse > {
	const { fieldKey, search, perPage, include } = params;

	const query = new URLSearchParams();

	if ( search !== undefined && search !== '' ) {
		query.set( 'search', search );
	}

	if ( perPage !== undefined ) {
		query.set( 'per_page', String( perPage ) );
	}

	if ( include !== undefined && include.length > 0 ) {
		query.set( 'include', include.join( ',' ) );
	}

	const qs = query.toString();
	const endpoint = `taxonomies/${ fieldKey }/terms${ qs ? `?${ qs }` : '' }`;

	return apiFetch( endpoint, TaxonomyTermsResponseSchema, { signal } );
}
