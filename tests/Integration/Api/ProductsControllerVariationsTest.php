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
use WC_Product_Variable;
use WC_Product_Variation;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests confirming that POST /products/query includes type and
 * variations_count, does NOT return product_variation rows, and that
 * PUT /products/batch can save variation fields with optimistic locking.
 */
final class ProductsControllerVariationsTest extends WP_UnitTestCase
{
    private DatabaseMigrator $migrator;

    public function set_up(): void
    {
        parent::set_up();

        $this->migrator = new DatabaseMigrator();
        $this->migrator->migrate();

        $migrator = $this->migrator;

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

        $userId = self::factory()->user->create(['role' => 'administrator']);
        $user = get_userdata($userId);
        $user->add_cap('edit_products');
        $user->add_cap('delete_products');
        wp_set_current_user($userId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createSimpleProduct(string $name = 'Simple Product'): int
    {
        $p = new WC_Product_Simple();
        $p->set_name($name);
        $p->set_regular_price('10.00');
        return $p->save();
    }

    /**
     * @return array{parent_id: int, variation_ids: list<int>}
     */
    private function createVariableProduct(string $name = 'Variable Product', int $variationCount = 2): array
    {
        $parent = new WC_Product_Variable();
        $parent->set_name($name);
        $parent->set_status('publish');
        $parentId = $parent->save();

        $variationIds = [];

        for ($i = 0; $i < $variationCount; $i++) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parentId);
            $variation->set_regular_price((string) (10 + $i));
            $variation->set_sku('V-' . $parentId . '-' . $i);
            $variation->set_status('publish');
            $variationIds[] = $variation->save();
        }

        return ['parent_id' => $parentId, 'variation_ids' => $variationIds];
    }

    private function queryProducts(): array
    {
        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/products/query');
        $request->set_body_params(['filters' => []]);
        $response = rest_get_server()->dispatch($request);
        self::assertSame(200, $response->get_status());
        return $response->get_data()['items'];
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_query_response_includes_type_field(): void
    {
        $this->createSimpleProduct();

        $items = $this->queryProducts();

        self::assertNotEmpty($items, 'Expected at least one product in response');

        foreach ($items as $item) {
            self::assertArrayHasKey('type', $item, 'Product response must include "type" field');
            self::assertIsString($item['type']);
        }
    }

    public function test_query_response_includes_variations_count(): void
    {
        $this->createSimpleProduct();

        $items = $this->queryProducts();

        self::assertNotEmpty($items);

        foreach ($items as $item) {
            self::assertArrayHasKey('variations_count', $item, 'Product response must include "variations_count"');
            self::assertIsInt($item['variations_count']);
        }
    }

    public function test_query_type_is_variable_for_variable_product(): void
    {
        $result = $this->createVariableProduct('My Variable', 2);

        $items = $this->queryProducts();

        $found = null;
        foreach ($items as $item) {
            if ($item['id'] === $result['parent_id']) {
                $found = $item;
                break;
            }
        }

        self::assertNotNull($found, 'Variable product should appear in query results');
        self::assertSame('variable', $found['type']);
        self::assertSame(2, $found['variations_count']);
    }

    public function test_query_type_is_simple_for_simple_product(): void
    {
        $simpleId = $this->createSimpleProduct('My Simple');

        $items = $this->queryProducts();

        $found = null;
        foreach ($items as $item) {
            if ($item['id'] === $simpleId) {
                $found = $item;
                break;
            }
        }

        self::assertNotNull($found, 'Simple product should appear in query results');
        self::assertSame('simple', $found['type']);
        self::assertSame(0, $found['variations_count']);
    }

    public function test_query_does_not_return_product_variation_post_type(): void
    {
        $result = $this->createVariableProduct('Variable', 3);

        $items = $this->queryProducts();

        // None of the returned IDs should be variation IDs.
        $returnedIds = array_column($items, 'id');
        foreach ($result['variation_ids'] as $varId) {
            self::assertNotContains(
                $varId,
                $returnedIds,
                "Variation ID {$varId} must NOT appear in main products/query response"
            );
        }
    }

    public function test_batch_save_can_update_variation_field(): void
    {
        $result = $this->createVariableProduct('Variable', 1);
        $variationId = $result['variation_ids'][0];
        $postModified = get_post_field('post_modified', $variationId);

        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params([
            'changes' => [
                [
                    'id'           => $variationId,
                    'field'        => 'sku',
                    'value'        => 'NEW-SKU-VAR',
                    'post_modified' => $postModified,
                ],
            ],
        ]);

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $data['success']);
        self::assertSame(0, $data['errors']);

        clean_post_cache($variationId);
        self::assertSame('NEW-SKU-VAR', wc_get_product($variationId)->get_sku());
    }

    public function test_batch_save_optimistic_lock_for_variation(): void
    {
        $result = $this->createVariableProduct('Variable', 1);
        $variationId = $result['variation_ids'][0];

        $request = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params([
            'changes' => [
                [
                    'id'           => $variationId,
                    'field'        => 'sku',
                    'value'        => 'STALE-SKU',
                    'post_modified' => '2000-01-01 00:00:00',
                ],
            ],
        ]);

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(0, $data['success']);
        self::assertSame(1, $data['errors']);
        self::assertSame('wbm_conflict', $data['results'][0]['code']);
    }
}
