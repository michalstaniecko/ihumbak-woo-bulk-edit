import { apiFetch } from './client';
import { ChangelogResponseSchema } from '@/types/api';
import type { ChangelogResponse, ChangelogQueryParams } from '@/types/api';

export function fetchChangelog(
	params: ChangelogQueryParams = {},
	signal?: AbortSignal
): Promise< ChangelogResponse > {
	const query = new URLSearchParams();

	if ( params.page !== undefined ) {
		query.set( 'page', String( params.page ) );
	}
	if ( params.per_page !== undefined ) {
		query.set( 'per_page', String( params.per_page ) );
	}
	if ( params.product_id !== undefined ) {
		query.set( 'product_id', String( params.product_id ) );
	}
	if ( params.user_id !== undefined ) {
		query.set( 'user_id', String( params.user_id ) );
	}
	if ( params.field ) {
		query.set( 'field', params.field );
	}
	if ( params.date_from ) {
		query.set( 'date_from', params.date_from );
	}
	if ( params.date_to ) {
		query.set( 'date_to', params.date_to );
	}

	const qs = query.toString();
	const endpoint = qs ? `changelog?${ qs }` : 'changelog';

	return apiFetch( endpoint, ChangelogResponseSchema, { signal } );
}
