<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\VariationsController;
use IhumbakWooBulkEdit\Query\VariationsRepository;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for GET /products/{id}/variations.
 */
final class VariationsControllerTest extends WP_UnitTestCase
{
    private int $adminUserId;
    private VariationsController $controller;

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new VariationsController(
            new VariationsRepository(),
            new CapabilityChecker(),
        );

        add_action('rest_api_init', function (): void {
            $this->controller->register_routes();
        });

        do_action('rest_api_init', rest_get_server());

        $this->adminUserId = self::factory()->user->create(['role' => 'administrator']);
        $user = get_userdata($this->adminUserId);
        $user->add_cap('edit_products');
        wp_set_current_user($this->adminUserId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createVariableProduct(int $variationCount = 2): array
    {
        $parent = new WC_Product_Variable();
        $parent->set_name('Variable Product');
        $parent->set_status('publish');
        $parentId = $parent->save();

        $variationIds = [];

        for ($i = 0; $i < $variationCount; $i++) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parentId);
            $variation->set_regular_price((string) (10 + $i));
            $variation->set_sku('VAR-' . $i);
            $variation->set_status('publish');
            $variationIds[] = $variation->save();
        }

        return ['parent_id' => $parentId, 'variation_ids' => $variationIds];
    }

    private function createSimpleProduct(): int
    {
        $simple = new WC_Product_Simple();
        $simple->set_name('Simple');
        $simple->set_regular_price('9.99');
        return $simple->save();
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_get_variations_returns_200_for_variable_product(): void
    {
        $result = $this->createVariableProduct(2);

        $request = new WP_REST_Request(
            'GET',
            '/ihumbak-woo-bulk-edit/v1/products/' . $result['parent_id'] . '/variations'
        );
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());
    }

    public function test_get_variations_returns_400_for_simple_product(): void
    {
        $simpleId = $this->createSimpleProduct();

        $request = new WP_REST_Request(
            'GET',
            '/ihumbak-woo-bulk-edit/v1/products/' . $simpleId . '/variations'
        );
        $response = rest_get_server()->dispatch($request);

        self::assertSame(400, $response->get_status());
        $data = $response->get_data();
        self::assertSame('wbm_not_variable', $data['code']);
    }

    public function test_get_variations_returns_404_for_nonexistent_id(): void
    {
        $request = new WP_REST_Request(
            'GET',
            '/ihumbak-woo-bulk-edit/v1/products/999999999/variations'
        );
        $response = rest_get_server()->dispatch($request);

        self::assertSame(404, $response->get_status());
        $data = $response->get_data();
        self::assertSame('wbm_not_found', $data['code']);
    }

    public function test_get_variations_unauthenticated_returns_error(): void
    {
        wp_set_current_user(0);

        $result = $this->createVariableProduct(1);

        $request = new WP_REST_Request(
            'GET',
            '/ihumbak-woo-bulk-edit/v1/products/' . $result['parent_id'] . '/variations'
        );
        $response = rest_get_server()->dispatch($request);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    public function test_get_variations_response_structure_matches_schema(): void
    {
        $result = $this->createVariableProduct(2);

        $request = new WP_REST_Request(
            'GET',
            '/ihumbak-woo-bulk-edit/v1/products/' . $result['parent_id'] . '/variations'
        );
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());

        $data = $response->get_data();

        // Top-level structure.
        self::assertArrayHasKey('parent_id', $data);
        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('total', $data);
        self::assertSame($result['parent_id'], $data['parent_id']);
        self::assertSame(2, $data['total']);
        self::assertCount(2, $data['items']);

        // Per-variation structure.
        $v = $data['items'][0];
        foreach ([
            'id', 'parent_id', 'name', 'sku', 'regular_price', 'sale_price',
            'stock_quantity', 'manage_stock', 'weight', 'length', 'width', 'height',
            'thumbnail_id', 'status', 'menu_order', 'attributes', 'post_modified',
        ] as $key) {
            self::assertArrayHasKey($key, $v, "Response missing key: {$key}");
        }

        self::assertSame($result['parent_id'], $v['parent_id']);
    }
}
