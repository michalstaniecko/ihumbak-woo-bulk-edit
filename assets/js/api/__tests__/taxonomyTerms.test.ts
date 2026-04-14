import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { fetchTaxonomyTerms } from '../taxonomyTerms';
import { ApiError } from '../client';

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
	vi.stubGlobal( 'fetch', vi.fn( async () => response ) );
}

const validResponse = {
	items: [
		{ id: 1, name: 'Shirts', slug: 'shirts', count: 5, parent: 0 },
		{ id: 2, name: 'Hats', slug: 'hats', count: 3, parent: 0 },
	],
	total: 2,
};

describe( 'fetchTaxonomyTerms', () => {
	it( 'calls GET /taxonomies/{fieldKey}/terms', async () => {
		mockFetchJson( validResponse );

		await fetchTaxonomyTerms( { fieldKey: 'categories' } );

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = (
			fetch as unknown as ReturnType< typeof vi.fn >
		).mock.calls[ 0 ];

		expect( url ).toContain( 'taxonomies/categories/terms' );
		expect( init.method ).toBe( 'GET' );
	} );

	it( 'includes search param in URL when provided', async () => {
		mockFetchJson( validResponse );

		await fetchTaxonomyTerms( { fieldKey: 'categories', search: 'shirt' } );

		const [ url ] = ( fetch as unknown as ReturnType< typeof vi.fn > ).mock
			.calls[ 0 ];
		expect( url ).toContain( 'search=shirt' );
	} );

	it( 'includes per_page param in URL when provided', async () => {
		mockFetchJson( validResponse );

		await fetchTaxonomyTerms( { fieldKey: 'tags', perPage: 10 } );

		const [ url ] = ( fetch as unknown as ReturnType< typeof vi.fn > ).mock
			.calls[ 0 ];
		expect( url ).toContain( 'per_page=10' );
	} );

	it( 'includes include param as CSV when provided', async () => {
		mockFetchJson( validResponse );

		await fetchTaxonomyTerms( { fieldKey: 'categories', include: [ 1, 2, 3 ] } );

		const [ url ] = ( fetch as unknown as ReturnType< typeof vi.fn > ).mock
			.calls[ 0 ];
		expect( url ).toContain( 'include=1%2C2%2C3' );
	} );

	it( 'returns validated TaxonomyTermsResponse', async () => {
		mockFetchJson( validResponse );

		const result = await fetchTaxonomyTerms( { fieldKey: 'categories' } );

		expect( result.total ).toBe( 2 );
		expect( result.items ).toHaveLength( 2 );
		expect( result.items[ 0 ].id ).toBe( 1 );
		expect( result.items[ 0 ].name ).toBe( 'Shirts' );
		expect( result.items[ 0 ].slug ).toBe( 'shirts' );
		expect( result.items[ 0 ].count ).toBe( 5 );
		expect( result.items[ 0 ].parent ).toBe( 0 );
	} );

	it( 'throws ApiError on 404 (unknown field)', async () => {
		mockFetchJson(
			{
				code: 'wbm_invalid_taxonomy_field',
				message: 'Unknown taxonomy field.',
				data: { status: 404 },
			},
			false,
			404
		);

		await expect(
			fetchTaxonomyTerms( { fieldKey: 'nonexistent' } )
		).rejects.toBeInstanceOf( ApiError );

		try {
			await fetchTaxonomyTerms( { fieldKey: 'nonexistent' } );
		} catch ( e ) {
			const err = e as ApiError;
			expect( err.code ).toBe( 'wbm_invalid_taxonomy_field' );
			expect( err.status ).toBe( 404 );
		}
	} );

	it( 'omits params that are not provided', async () => {
		mockFetchJson( validResponse );

		await fetchTaxonomyTerms( { fieldKey: 'tags' } );

		const [ url ] = ( fetch as unknown as ReturnType< typeof vi.fn > ).mock
			.calls[ 0 ];
		expect( url ).not.toContain( 'search=' );
		expect( url ).not.toContain( 'include=' );
		expect( url ).not.toContain( 'per_page=' );
	} );

	it( 'uses X-WP-Nonce header from iwbeData', async () => {
		mockFetchJson( validResponse );

		await fetchTaxonomyTerms( { fieldKey: 'categories' } );

		const [ , init ] = ( fetch as unknown as ReturnType< typeof vi.fn > ).mock
			.calls[ 0 ];
		expect( init.headers[ 'X-WP-Nonce' ] ).toBe( 'test-nonce' );
	} );
} );
