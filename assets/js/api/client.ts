import { __, sprintf } from '@wordpress/i18n';
import { z } from 'zod';
import { ApiErrorSchema } from '@/types/api';
import type { ApiErrorResponse } from '@/types/api';

/**
 * Custom error class for API errors with structured code and status.
 */
export class ApiError extends Error {
	public readonly code: string;
	public readonly status: number;

	constructor( code: string, message: string, status: number ) {
		super( message );
		this.name = 'ApiError';
		this.code = code;
		this.status = status;
	}

	static fromResponse( body: ApiErrorResponse ): ApiError {
		return new ApiError( body.code, body.message, body.data.status );
	}
}

interface RequestOptions {
	method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
	body?: unknown;
	signal?: AbortSignal;
}

/**
 * Generic REST API fetch wrapper.
 *
 * - Prepends iwbeData.restUrl and injects X-WP-Nonce header
 * - Parses wbm_* error format on non-OK responses
 * - Validates successful responses against the provided Zod schema
 *
 * @template T
 * @param {string}         endpoint REST endpoint path relative to the API namespace.
 * @param {z.ZodType}      schema   Zod schema to validate the response against.
 * @param {RequestOptions} options  Fetch options (method, body, signal).
 */
export async function apiFetch< T >(
	endpoint: string,
	schema: z.ZodType< T >,
	options: RequestOptions = {}
): Promise< T > {
	const { method = 'GET', body, signal } = options;

	const url = `${ iwbeData.restUrl }${ endpoint }`;

	const headers: HeadersInit = {
		'X-WP-Nonce': iwbeData.nonce,
		'Content-Type': 'application/json',
	};

	const response = await fetch( url, {
		method,
		headers,
		body: body !== undefined ? JSON.stringify( body ) : undefined,
		signal,
	} );

	const json: unknown = await response.json();

	if ( ! response.ok ) {
		const errorParse = ApiErrorSchema.safeParse( json );
		if ( errorParse.success ) {
			throw ApiError.fromResponse( errorParse.data );
		}
		throw new ApiError(
			'wbm_unknown_error',
			__( 'An unexpected error occurred.', 'ihumbak-woo-bulk-edit' ),
			response.status
		);
	}

	const result = schema.safeParse( json );
	if ( ! result.success ) {
		throw new ApiError(
			'wbm_validation_error',
			sprintf(
				/* translators: %s: validation error details */
				__(
					'Invalid response from server: %s',
					'ihumbak-woo-bulk-edit'
				),
				result.error.message
			),
			500
		);
	}

	return result.data;
}
