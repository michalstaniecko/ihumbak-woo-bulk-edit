import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { bulkDuplicateProducts } from '../products';
import { ApiError } from '../client';

// Mirror the iwbeData stub used by bulkDeleteProducts.test.ts so apiFetch can
// resolve the REST URL and nonce.
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

describe( 'bulkDuplicateProducts', () => {
	it( 'POSTs to /products/duplicate with the correct method, headers and body', async () => {
		mockFetchJson( {
			results: [ { status: 'success', id: 1, new_id: 101 } ],
			total: 1,
			success: 1,
			errors: 0,
		} );

		await bulkDuplicateProducts( {
			ids: [ 1, 2 ],
			copy_meta: true,
			copy_images: false,
		} );

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = (
			fetch as unknown as ReturnType< typeof vi.fn >
		).mock.calls[ 0 ];

		expect( url ).toBe(
			'https://example.test/wp-json/ihumbak-woo-bulk-edit/v1/products/duplicate'
		);
		expect( init.method ).toBe( 'POST' );
		expect( init.headers[ 'X-WP-Nonce' ] ).toBe( 'test-nonce' );
		expect( init.headers[ 'Content-Type' ] ).toBe( 'application/json' );
		expect( JSON.parse( init.body as string ) ).toEqual( {
			ids: [ 1, 2 ],
			copy_meta: true,
			copy_images: false,
		} );
	} );

	it( 'parses a valid success response (single product)', async () => {
		mockFetchJson( {
			results: [ { status: 'success', id: 1, new_id: 101 } ],
			total: 1,
			success: 1,
			errors: 0,
		} );

		const result = await bulkDuplicateProducts( {
			ids: [ 1 ],
			copy_meta: true,
			copy_images: true,
		} );

		expect( result.total ).toBe( 1 );
		expect( result.success ).toBe( 1 );
		expect( result.errors ).toBe( 0 );
		expect( result.results ).toHaveLength( 1 );
		expect( result.results[ 0 ].status ).toBe( 'success' );
		expect( result.results[ 0 ].id ).toBe( 1 );
		expect( result.results[ 0 ].new_id ).toBe( 101 );
	} );

	it( 'parses a partial-failure response (success + error rows)', async () => {
		mockFetchJson( {
			results: [
				{ status: 'success', id: 1, new_id: 101 },
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
		} );

		const result = await bulkDuplicateProducts( {
			ids: [ 1, 999 ],
			copy_meta: true,
			copy_images: true,
		} );

		expect( result.success ).toBe( 1 );
		expect( result.errors ).toBe( 1 );
		expect( result.results[ 1 ].status ).toBe( 'error' );
		expect( result.results[ 1 ].code ).toBe( 'wbm_not_found' );
		// `new_id` is optional and absent on error rows.
		expect( result.results[ 1 ].new_id ).toBeUndefined();
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
			bulkDuplicateProducts( {
				ids: [ 1 ],
				copy_meta: true,
				copy_images: true,
			} )
		).rejects.toBeInstanceOf( ApiError );
	} );

	it( 'throws an ApiError with wbm_too_many_ids on 400', async () => {
		mockFetchJson(
			{
				code: 'wbm_too_many_ids',
				message: 'Too many product IDs in a single request (maximum 100).',
				data: { status: 400 },
			},
			false,
			400
		);

		await expect(
			bulkDuplicateProducts( {
				ids: Array.from( { length: 101 }, ( _, i ) => i + 1 ),
				copy_meta: true,
				copy_images: true,
			} )
		).rejects.toMatchObject( {
			code: 'wbm_too_many_ids',
			status: 400,
		} );
	} );

	it( 'throws a validation error on malformed success response', async () => {
		mockFetchJson( {
			results: [ { status: 'success', id: 1, new_id: 101 } ],
			total: 1,
			success: 1,
			// Missing required `errors` field — Zod should reject.
		} );

		await expect(
			bulkDuplicateProducts( {
				ids: [ 1 ],
				copy_meta: true,
				copy_images: true,
			} )
		).rejects.toMatchObject( {
			code: 'wbm_validation_error',
		} );
	} );
} );
