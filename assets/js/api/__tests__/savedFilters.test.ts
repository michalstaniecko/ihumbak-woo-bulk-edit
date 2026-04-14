import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
	fetchSavedFilters,
	createSavedFilter,
	updateSavedFilter,
	deleteSavedFilter,
} from '../savedFilters';
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

const validFilter = {
	id: 42,
	user_id: 7,
	name: 'Out of stock',
	definition: { filters: [], search: '', sort: { field: 'name', order: 'asc' } },
	is_shared: false,
	created_at: '2024-01-01 00:00:00',
	updated_at: '2024-01-01 00:00:00',
};

describe( 'fetchSavedFilters', () => {
	it( 'calls GET /filters', async () => {
		mockFetchJson( { items: [ validFilter ] } );

		const result = await fetchSavedFilters();

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = (
			fetch as unknown as ReturnType< typeof vi.fn >
		).mock.calls[ 0 ];

		expect( url ).toBe(
			'https://example.test/wp-json/ihumbak-woo-bulk-edit/v1/filters'
		);
		expect( init.method ).toBe( 'GET' );
		expect( result.items ).toHaveLength( 1 );
		expect( result.items[ 0 ].id ).toBe( 42 );
	} );
} );

describe( 'createSavedFilter', () => {
	it( 'sends POST with JSON body', async () => {
		mockFetchJson( validFilter, true, 201 );

		const payload = {
			name: 'My Filter',
			definition: { filters: [], search: '' },
			is_shared: false,
		};

		await createSavedFilter( payload );

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = (
			fetch as unknown as ReturnType< typeof vi.fn >
		).mock.calls[ 0 ];

		expect( url ).toBe(
			'https://example.test/wp-json/ihumbak-woo-bulk-edit/v1/filters'
		);
		expect( init.method ).toBe( 'POST' );
		expect( init.headers[ 'Content-Type' ] ).toBe( 'application/json' );
		const body = JSON.parse( init.body as string );
		expect( body.name ).toBe( 'My Filter' );
		expect( body.is_shared ).toBe( false );
	} );

	it( 'surfaces ApiError on 409 (name conflict)', async () => {
		mockFetchJson(
			{
				code: 'wbm_filter_name_conflict',
				message: 'A filter named "My Filter" already exists.',
				data: { status: 409 },
			},
			false,
			409
		);

		await expect(
			createSavedFilter( {
				name: 'My Filter',
				definition: { filters: [] },
				is_shared: false,
			} )
		).rejects.toBeInstanceOf( ApiError );

		try {
			await createSavedFilter( {
				name: 'My Filter',
				definition: { filters: [] },
				is_shared: false,
			} );
		} catch ( e ) {
			expect( e ).toBeInstanceOf( ApiError );
			const err = e as ApiError;
			expect( err.code ).toBe( 'wbm_filter_name_conflict' );
			expect( err.status ).toBe( 409 );
		}
	} );
} );

describe( 'updateSavedFilter', () => {
	it( 'sends PUT to /filters/{id}', async () => {
		mockFetchJson( { ...validFilter, name: 'Updated Name' } );

		await updateSavedFilter( 42, { name: 'Updated Name' } );

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = (
			fetch as unknown as ReturnType< typeof vi.fn >
		).mock.calls[ 0 ];

		expect( url ).toBe(
			'https://example.test/wp-json/ihumbak-woo-bulk-edit/v1/filters/42'
		);
		expect( init.method ).toBe( 'PUT' );
		const body = JSON.parse( init.body as string );
		expect( body.name ).toBe( 'Updated Name' );
	} );
} );

describe( 'deleteSavedFilter', () => {
	it( 'sends DELETE to /filters/{id}', async () => {
		mockFetchJson( { deleted: true, id: 42 } );

		const result = await deleteSavedFilter( 42 );

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = (
			fetch as unknown as ReturnType< typeof vi.fn >
		).mock.calls[ 0 ];

		expect( url ).toBe(
			'https://example.test/wp-json/ihumbak-woo-bulk-edit/v1/filters/42'
		);
		expect( init.method ).toBe( 'DELETE' );
		expect( result.deleted ).toBe( true );
		expect( result.id ).toBe( 42 );
	} );
} );
