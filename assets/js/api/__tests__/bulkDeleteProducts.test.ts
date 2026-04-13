import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { bulkDeleteProducts } from '../products';
import { ApiError } from '../client';

// `apiFetch` (in client.ts) reads `iwbeData` from the global. Install a stub
// before any test runs so the URL / nonce lookups succeed.
beforeEach( () => {
	( globalThis as unknown as { iwbeData: unknown } ).iwbeData = {
		restUrl: 'https://example.test/wp-json/ihumbak-woo-bulk-edit/v1/',
		nonce: 'test-nonce',
		adminUrl: 'https://example.test/wp-admin/',
	};
} );

afterEach( () => {
	vi.restoreAllMocks();
	delete ( globalThis as Partial< { iwbeData: unknown } > ).iwbeData;
} );

function mockFetchJson( body: unknown, ok = true, status = 200 ): void {
	const response = {
		ok,
		status,
		json: async () => body,
	} as unknown as Response;
	vi.stubGlobal(
		'fetch',
		vi.fn( async () => response )
	);
}

describe( 'bulkDeleteProducts', () => {
	it( 'POSTs the correct URL, method, headers and body', async () => {
		mockFetchJson( {
			results: [ { status: 'success', id: 1 } ],
			total: 1,
			success: 1,
			errors: 0,
			mode: 'trash',
		} );

		await bulkDeleteProducts( { ids: [ 1, 2 ], mode: 'trash' } );

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = (
			fetch as unknown as ReturnType< typeof vi.fn >
		).mock.calls[ 0 ];

		expect( url ).toBe(
			'https://example.test/wp-json/ihumbak-woo-bulk-edit/v1/products/batch'
		);
		expect( init.method ).toBe( 'DELETE' );
		expect( init.headers[ 'X-WP-Nonce' ] ).toBe( 'test-nonce' );
		expect( init.headers[ 'Content-Type' ] ).toBe( 'application/json' );
		expect( JSON.parse( init.body as string ) ).toEqual( {
			ids: [ 1, 2 ],
			mode: 'trash',
		} );
	} );

	it( 'parses and returns a valid trash-mode response', async () => {
		mockFetchJson( {
			results: [
				{ status: 'success', id: 1 },
				{ status: 'success', id: 2 },
			],
			total: 2,
			success: 2,
			errors: 0,
			mode: 'trash',
		} );

		const result = await bulkDeleteProducts( { ids: [ 1, 2 ], mode: 'trash' } );

		expect( result.total ).toBe( 2 );
		expect( result.success ).toBe( 2 );
		expect( result.mode ).toBe( 'trash' );
		expect( result.results ).toHaveLength( 2 );
	} );

	it( 'parses and returns a valid permanent-mode response', async () => {
		mockFetchJson( {
			results: [ { status: 'success', id: 7 } ],
			total: 1,
			success: 1,
			errors: 0,
			mode: 'permanent',
		} );

		const result = await bulkDeleteProducts( {
			ids: [ 7 ],
			mode: 'permanent',
		} );

		expect( result.mode ).toBe( 'permanent' );
	} );

	it( 'surfaces partial failures (success + errors counts)', async () => {
		mockFetchJson( {
			results: [
				{ status: 'success', id: 1 },
				{
					status: 'error',
					id: 999,
					code: 'wbm_not_found',
					message: 'Product not found.',
				},
			],
			total: 2,
			success: 1,
			errors: 1,
			mode: 'trash',
		} );

		const result = await bulkDeleteProducts( {
			ids: [ 1, 999 ],
			mode: 'trash',
		} );

		expect( result.success ).toBe( 1 );
		expect( result.errors ).toBe( 1 );
		expect( result.results[ 1 ].status ).toBe( 'error' );
		expect( result.results[ 1 ].code ).toBe( 'wbm_not_found' );
	} );

	it( 'throws an ApiError with wbm_forbidden on 403', async () => {
		mockFetchJson(
			{
				code: 'wbm_forbidden',
				message: 'You do not have permission.',
				data: { status: 403 },
			},
			false,
			403
		);

		await expect(
			bulkDeleteProducts( { ids: [ 1 ], mode: 'permanent' } )
		).rejects.toBeInstanceOf( ApiError );

		try {
			await bulkDeleteProducts( { ids: [ 1 ], mode: 'permanent' } );
		} catch ( e ) {
			expect( e ).toBeInstanceOf( ApiError );
			const err = e as ApiError;
			expect( err.code ).toBe( 'wbm_forbidden' );
			expect( err.status ).toBe( 403 );
		}
	} );

	it( 'throws an ApiError with wbm_invalid_ids on 400', async () => {
		mockFetchJson(
			{
				code: 'wbm_invalid_ids',
				message: 'No valid product IDs provided.',
				data: { status: 400 },
			},
			false,
			400
		);

		await expect(
			bulkDeleteProducts( { ids: [], mode: 'trash' } )
		).rejects.toMatchObject( {
			code: 'wbm_invalid_ids',
			status: 400,
		} );
	} );

	it( 'throws a validation error on malformed success response', async () => {
		mockFetchJson( {
			results: [],
			total: 1,
			success: 1,
			errors: 0,
			// Missing required `mode` field — Zod should reject.
		} );

		await expect(
			bulkDeleteProducts( { ids: [ 1 ], mode: 'trash' } )
		).rejects.toMatchObject( {
			code: 'wbm_validation_error',
		} );
	} );
} );
