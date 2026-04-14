<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\ProductsController;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Operations\BulkDelete;
use IhumbakWooBulkEdit\Operations\BulkDuplicate;
use IhumbakWooBulkEdit\Persistence\BatchSaver;
use IhumbakWooBulkEdit\Persistence\ChangeLogRepository;
use IhumbakWooBulkEdit\Persistence\DatabaseMigrator;
use IhumbakWooBulkEdit\Persistence\ProductSaver;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use IhumbakWooBulkEdit\Security\RateLimiter;
use WC_Product_Simple;
use WP_REST_Request;
use WP_UnitTestCase;

final class ProductsControllerTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        $migrator = new DatabaseMigrator();
        $migrator->migrate();

        add_action('rest_api_init', static function () use ($migrator): void {
            $fieldRegistry = new FieldRegistry();
            $changeLog     = new ChangeLogRepository($migrator);
            $controller    = new ProductsController(
                $fieldRegistry,
                new CapabilityChecker(),
                new RateLimiter(),
                new BatchSaver(new ProductSaver($fieldRegistry), $changeLog),
                new BulkDelete($changeLog),
                new BulkDuplicate($changeLog),
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

    private function createSimpleProduct(string $name = 'Test Product'): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price('10');
        return $product->save();
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
        $id           = $this->createSimpleProduct('Original Name');
        $postModified = get_post_field('post_modified', $id);

        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params([
            'changes' => [
                [
                    'id'           => $id,
                    'field'        => 'name',
                    'value'        => 'New Name',
                    'post_modified' => $postModified,
                ],
            ],
        ]);
        $response = rest_get_server()->dispatch($request);
        $data     = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['success']);
        self::assertSame(0, $data['errors']);
        self::assertSame('success', $data['results'][0]['status']);
        self::assertSame($id, $data['results'][0]['id']);

        // Verify the change was persisted.
        clean_post_cache($id);
        self::assertSame('New Name', wc_get_product($id)->get_name());
    }

    public function test_batch_save_with_nonexistent_id_returns_200_with_error_row(): void
    {
        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params([
            'changes' => [
                [
                    'id'            => 999999,
                    'field'         => 'name',
                    'value'         => 'Whatever',
                    'post_modified' => '2000-01-01 00:00:00',
                ],
            ],
        ]);
        $response = rest_get_server()->dispatch($request);
        $data     = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $data['total']);
        self::assertSame(0, $data['success']);
        self::assertSame(1, $data['errors']);
        self::assertSame('error', $data['results'][0]['status']);
        self::assertSame('wbm_not_found', $data['results'][0]['code']);
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
        // NOTE: The change items here intentionally use the old/wrong shape
        // (missing 'field', 'value', 'post_modified'). That is acceptable because
        // the rate-limiter fires before body validation, so the 429 is returned
        // before the controller ever inspects the item structure.
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
