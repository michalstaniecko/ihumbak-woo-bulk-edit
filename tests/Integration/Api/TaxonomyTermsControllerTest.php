<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\TaxonomyTermsController;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for TaxonomyTermsController.
 *
 * Route: GET /ihumbak-woo-bulk-edit/v1/taxonomies/{field_key}/terms
 *
 * @covers \IhumbakWooBulkEdit\Api\TaxonomyTermsController
 */
final class TaxonomyTermsControllerTest extends WP_UnitTestCase
{
    private int $capableUserId;

    public function set_up(): void
    {
        parent::set_up();

        // Register the controller routes.
        add_action('rest_api_init', static function (): void {
            $controller = new TaxonomyTermsController(new CapabilityChecker());
            $controller->register_routes();
        });

        // Force REST server to re-initialize.
        do_action('rest_api_init', rest_get_server());

        // Create a capable user.
        $this->capableUserId = (int) self::factory()->user->create(['role' => 'administrator']);

        // Ensure the relevant taxonomies are registered.
        if (! taxonomy_exists('product_cat')) {
            register_taxonomy('product_cat', 'product', ['hierarchical' => true]);
        }
        if (! taxonomy_exists('product_tag')) {
            register_taxonomy('product_tag', 'product', ['hierarchical' => false]);
        }
        if (! taxonomy_exists('product_shipping_class')) {
            register_taxonomy('product_shipping_class', 'product', ['hierarchical' => false]);
        }
    }

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401_or_403(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    // ── 404 for unknown field_key ─────────────────────────────────────────────

    public function test_unknown_field_key_returns_404(): void
    {
        wp_set_current_user($this->capableUserId);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/nonexistent_field/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(404, $response->get_status());

        $data = $response->get_data();
        self::assertIsArray($data);
        self::assertSame('wbm_invalid_taxonomy_field', $data['code']);
    }

    public function test_non_taxonomy_field_returns_404(): void
    {
        wp_set_current_user($this->capableUserId);

        // 'name' is a valid field, but it's not a taxonomy field.
        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/name/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(404, $response->get_status());

        $data = $response->get_data();
        self::assertIsArray($data);
        self::assertSame('wbm_invalid_taxonomy_field', $data['code']);
    }

    public function test_regular_price_field_returns_404(): void
    {
        wp_set_current_user($this->capableUserId);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/regular_price/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(404, $response->get_status());
    }

    // ── Basic response structure ──────────────────────────────────────────────

    public function test_categories_returns_200_with_correct_shape(): void
    {
        wp_set_current_user($this->capableUserId);

        // Create some terms so the response is non-empty.
        wp_insert_term('Shirts', 'product_cat');
        wp_insert_term('Pants', 'product_cat');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertIsArray($data);
        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('total', $data);
        self::assertIsArray($data['items']);
        self::assertIsInt($data['total']);
    }

    public function test_response_items_have_required_fields(): void
    {
        wp_set_current_user($this->capableUserId);

        wp_insert_term('Electronics', 'product_cat');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertNotEmpty($data['items']);

        $item = $data['items'][0];
        self::assertArrayHasKey('id', $item);
        self::assertArrayHasKey('name', $item);
        self::assertArrayHasKey('slug', $item);
        self::assertArrayHasKey('count', $item);
        self::assertArrayHasKey('parent', $item);

        self::assertIsInt($item['id']);
        self::assertIsString($item['name']);
        self::assertIsString($item['slug']);
        self::assertIsInt($item['count']);
        self::assertIsInt($item['parent']);
    }

    // ── Tags field ────────────────────────────────────────────────────────────

    public function test_tags_field_returns_terms(): void
    {
        wp_set_current_user($this->capableUserId);

        wp_insert_term('summer', 'product_tag');
        wp_insert_term('sale', 'product_tag');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/tags/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        $names = array_column($data['items'], 'name');

        self::assertContains('summer', $names);
        self::assertContains('sale', $names);
    }

    // ── Shipping class field ──────────────────────────────────────────────────

    public function test_shipping_class_field_returns_terms(): void
    {
        wp_set_current_user($this->capableUserId);

        wp_insert_term('Heavy', 'product_shipping_class');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/shipping_class/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        $names = array_column($data['items'], 'name');

        self::assertContains('Heavy', $names);
    }

    // ── Search parameter ──────────────────────────────────────────────────────

    public function test_search_param_filters_results(): void
    {
        wp_set_current_user($this->capableUserId);

        wp_insert_term('Shirts Long', 'product_cat');
        wp_insert_term('Shirts Short', 'product_cat');
        wp_insert_term('Hats', 'product_cat');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $request->set_param('search', 'Shirts');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        $names = array_column($data['items'], 'name');

        self::assertContains('Shirts Long', $names);
        self::assertContains('Shirts Short', $names);
        self::assertNotContains('Hats', $names);
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        wp_set_current_user($this->capableUserId);

        wp_insert_term('Shoes', 'product_cat');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $request->set_param('search', 'xyzzy_nonexistent_term');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertSame(0, $data['total']);
        self::assertEmpty($data['items']);
    }

    // ── per_page parameter ────────────────────────────────────────────────────

    public function test_per_page_limits_results(): void
    {
        wp_set_current_user($this->capableUserId);

        // Create 5 terms.
        for ($i = 1; $i <= 5; $i++) {
            wp_insert_term("Category {$i}", 'product_cat');
        }

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $request->set_param('per_page', 2);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        // Items in the response should be at most 2.
        self::assertLessThanOrEqual(2, count($data['items']));
        // Total should reflect all available terms.
        self::assertGreaterThanOrEqual(5, $data['total']);
    }

    public function test_per_page_defaults_to_30(): void
    {
        wp_set_current_user($this->capableUserId);

        // Total should not exceed 30 by default.
        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertLessThanOrEqual(30, count($data['items']));
    }

    public function test_per_page_max_is_100(): void
    {
        wp_set_current_user($this->capableUserId);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $request->set_param('per_page', 999);
        $response = rest_get_server()->dispatch($request);

        // The request should succeed but clamp per_page to 100.
        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertLessThanOrEqual(100, count($data['items']));
    }

    // ── include parameter ─────────────────────────────────────────────────────

    public function test_include_returns_only_specified_ids(): void
    {
        wp_set_current_user($this->capableUserId);

        $term1 = wp_insert_term('Alpha', 'product_cat');
        $term2 = wp_insert_term('Beta', 'product_cat');
        wp_insert_term('Gamma', 'product_cat');

        self::assertIsArray($term1);
        self::assertIsArray($term2);

        $id1 = (int) $term1['term_id'];
        $id2 = (int) $term2['term_id'];

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $request->set_param('include', "{$id1},{$id2}");
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        $ids = array_column($data['items'], 'id');

        self::assertContains($id1, $ids);
        self::assertContains($id2, $ids);
        self::assertNotContains('Gamma', array_column($data['items'], 'name'));
        self::assertSame(2, $data['total']);
    }

    public function test_include_bypasses_search(): void
    {
        wp_set_current_user($this->capableUserId);

        $term = wp_insert_term('Unique Term XYZ', 'product_cat');
        self::assertIsArray($term);
        $id = (int) $term['term_id'];

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $request->set_param('include', (string) $id);
        $request->set_param('search', 'something_else_entirely');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        $names = array_column($data['items'], 'name');
        self::assertContains('Unique Term XYZ', $names);
    }

    // ── total matches item count for exact searches ───────────────────────────

    public function test_total_reflects_all_matching_terms(): void
    {
        wp_set_current_user($this->capableUserId);

        wp_insert_term('Red Shoes', 'product_cat');
        wp_insert_term('Red Hats', 'product_cat');
        wp_insert_term('Blue Shoes', 'product_cat');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/taxonomies/categories/terms');
        $request->set_param('search', 'Red');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        $matchCount = count(array_filter($data['items'], fn($item) => str_contains($item['name'], 'Red')));
        self::assertGreaterThanOrEqual(2, $matchCount);
        self::assertGreaterThanOrEqual(2, $data['total']);
    }
}
