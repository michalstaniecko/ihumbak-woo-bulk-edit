<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\ProductsController;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use IhumbakWooBulkEdit\Security\RateLimiter;
use WP_REST_Request;
use WP_UnitTestCase;

final class ProductsControllerTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        add_action('rest_api_init', static function (): void {
            $controller = new ProductsController(
                new FieldRegistry(),
                new CapabilityChecker(),
                new RateLimiter(),
            );
            $controller->register_routes();
        });

        do_action('rest_api_init', rest_get_server());

        // Create admin with WooCommerce capabilities
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        $user = get_userdata($user_id);
        $user->add_cap('edit_products');
        $user->add_cap('delete_products');
        wp_set_current_user($user_id);
    }

    // --- POST /products/query ---

    public function test_query_returns_200_with_empty_filters(): void
    {
        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/products/query');
        $request->set_body_params(['filters' => []]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());
    }

    public function test_query_response_structure(): void
    {
        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/products/query');
        $request->set_body_params(['filters' => []]);
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('total', $data);
        self::assertArrayHasKey('page', $data);
        self::assertArrayHasKey('per_page', $data);
        self::assertArrayHasKey('pages', $data);
    }

    public function test_query_with_invalid_filter_returns_error(): void
    {
        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/products/query');
        $request->set_body_params([
            'filters' => [
                ['field' => 'nonexistent', 'operator' => '=', 'value' => 'test'],
            ],
        ]);
        $response = rest_get_server()->dispatch($request);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    public function test_query_unauthenticated_returns_error(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/products/query');
        $request->set_body_params(['filters' => []]);
        $response = rest_get_server()->dispatch($request);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    // --- PUT /products/batch ---

    public function test_batch_save_without_changes_returns_400(): void
    {
        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params([]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(400, $response->get_status());
    }

    public function test_batch_save_with_empty_changes_returns_400(): void
    {
        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params(['changes' => []]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(400, $response->get_status());
    }

    public function test_batch_save_with_valid_changes_returns_200(): void
    {
        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params(['changes' => [['id' => 1, 'name' => 'New Name']]]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());
    }

    // --- DELETE /products/batch ---

    public function test_batch_delete_without_ids_returns_400(): void
    {
        $request = new WP_REST_Request('DELETE', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params([]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(400, $response->get_status());
    }

    public function test_batch_delete_with_empty_ids_returns_400(): void
    {
        $request = new WP_REST_Request('DELETE', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params(['ids' => []]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(400, $response->get_status());
    }

    public function test_batch_delete_with_valid_ids_returns_200(): void
    {
        $request = new WP_REST_Request('DELETE', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params(['ids' => [1, 2]]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());
    }

    // --- Rate Limiting ---

    public function test_batch_save_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
            $request->set_body_params(['changes' => [['id' => 1, 'name' => 'Name']]]);
            rest_get_server()->dispatch($request);
        }

        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params(['changes' => [['id' => 1, 'name' => 'Name']]]);
        $response = rest_get_server()->dispatch($request);

        self::assertSame(429, $response->get_status());
    }
}
