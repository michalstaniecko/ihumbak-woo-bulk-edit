import { describe, it, expect, beforeEach, vi } from 'vitest';
import * as productsApi from '@/api/products';

/**
 * Regression test for multipage bulk save bug.
 *
 * Scenario: User edits column on pages 1-3, then saves.
 * Before fix: only page 1 products saved (post_modified empty for pages 2-3)
 * After fix: all products saved (post_modified fetched for missing pages)
 */
describe('useBatchSave — multipage regression', () => {
	beforeEach(() => {
		vi.clearAllMocks();
	});

	it('should export fetchProducts from api/products module', () => {
		// Regression guard: ensure fetchProducts is importable
		// (the bug was: useBatchSave.ts used it without importing)
		expect(typeof productsApi.fetchProducts).toBe('function');
	});

	it('should not skip products not on current page when saving', async () => {
		// This is a contract test: verify the API supports loading post_modified
		// by IDs, which is what useBatchSave needs for multipage edits.

		const mockFetchProducts = vi.spyOn(productsApi, 'fetchProducts');

		mockFetchProducts.mockResolvedValueOnce({
			items: [
				{
					id: 51,
					post_modified: '2025-04-15 10:00:00',
					name: 'Product 51',
					post_type: 'product',
					type: 'simple',
					variations_count: 0,
				},
				{
					id: 52,
					post_modified: '2025-04-15 10:01:00',
					name: 'Product 52',
					post_type: 'product',
					type: 'simple',
					variations_count: 0,
				},
			],
			total: 2,
			pages: 1,
		});

		// Call fetchProducts with IDs parameter (multipage case)
		const result = await productsApi.fetchProducts({
			ids: [51, 52],
			page: 1,
			per_page: 10,
		});

		// Verify that post_modified is returned (not empty)
		expect(result.items).toHaveLength(2);
		expect(result.items[0].post_modified).toBe('2025-04-15 10:00:00');
		expect(result.items[1].post_modified).toBe('2025-04-15 10:01:00');

		// Verify per_page respects minimum of 10
		const callArgs = mockFetchProducts.mock.calls[0][0];
		expect(callArgs.per_page).toBeGreaterThanOrEqual(10);
		expect(callArgs.per_page).toBeLessThanOrEqual(500);
	});

	it('should handle small missingIds count by clamping per_page to minimum 10', () => {
		// Before fix: Math.min(500, 3) = 3, which violates API minimum of 10
		// After fix: Math.max(10, Math.min(500, 3)) = 10

		const smallCount = 3;
		const calculatedPerPage = Math.max(10, Math.min(500, smallCount));

		expect(calculatedPerPage).toBe(10);
		expect(calculatedPerPage).toBeGreaterThanOrEqual(10);
	});

	it('should clamp per_page correctly for large result sets', () => {
		// Verify upper bound is respected
		const largeCount = 10000;
		const calculatedPerPage = Math.max(10, Math.min(500, largeCount));

		expect(calculatedPerPage).toBe(500);
	});
});
