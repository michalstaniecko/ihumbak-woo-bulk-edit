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
 * Integration tests for DELETE /products/batch (bulk delete).
 *
 * @license GPL-2.0-or-later
 */
final class ProductsControllerDeleteTest extends WP_UnitTestCase
{
    private DatabaseMigrator $migrator;
    private ChangeLogRepository $changeLog;
    private int $editorUserId;
    private int $adminUserId;

    public function set_up(): void
    {
        parent::set_up();

        $this->migrator = new DatabaseMigrator();
        $this->migrator->migrate();
        $this->changeLog = new ChangeLogRepository($this->migrator);

        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $this->migrator->changeLogTable());

        $migrator  = $this->migrator;
        $changeLog = $this->changeLog;
        add_action('rest_api_init', static function () use ($migrator, $changeLog): void {
            $fieldRegistry = new FieldRegistry();
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

        // Editor — only edit_products. Should be able to trash but NOT permanently delete.
        $this->editorUserId = self::factory()->user->create(['role' => 'editor']);
        $editor = get_userdata($this->editorUserId);
        $editor->add_cap('edit_products');
        // Defensive: ensure delete_products is NOT present on this editor.
        $editor->remove_cap('delete_products');

        // Administrator — full WC caps.
        $this->adminUserId = self::factory()->user->create(['role' => 'administrator']);
        $admin = get_userdata($this->adminUserId);
        $admin->add_cap('edit_products');
        $admin->add_cap('delete_products');
    }

    private function createSimpleProduct(string $name = 'Test Product', string $sku = ''): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        if ($sku !== '') {
            $product->set_sku($sku);
        }
        $product->set_regular_price('10');
        return $product->save();
    }

    private function createVariableProductWithVariations(int $variationCount = 2): array
    {
        $product = new WC_Product_Variable();
        $product->set_name('Variable Parent');
        $product->set_sku('VAR-PARENT-' . uniqid());
        $parentId = $product->save();

        $variationIds = [];
        for ($i = 0; $i < $variationCount; $i++) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parentId);
            $variation->set_regular_price((string) (10 + $i));
            $variationIds[] = $variation->save();
        }

        return [$parentId, $variationIds];
    }

    private function dispatchDelete(array $body): \WP_REST_Response
    {
        $request = new WP_REST_Request('DELETE', '/ihumbak-woo-bulk-edit/v1/products/batch');
        $request->set_body_params($body);
        return rest_get_server()->dispatch($request);
    }

    // --- Mode: trash ---

    public function test_trash_mode_as_editor_moves_product_to_trash(): void
    {
        wp_set_current_user($this->editorUserId);
        $id = $this->createSimpleProduct('Trashable', 'SKU-TRASH-1');

        $response = $this->dispatchDelete(['ids' => [$id], 'mode' => 'trash']);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame('trash', $data['mode']);
        self::assertSame(1, $data['success']);
        self::assertSame(0, $data['errors']);
        self::assertSame(1, $data['total']);
        self::assertSame('trash', get_post_status($id));
    }

    public function test_permanent_mode_as_editor_returns_403_wbm_forbidden(): void
    {
        wp_set_current_user($this->editorUserId);
        $id = $this->createSimpleProduct('NoPermDelete', 'SKU-NOP-1');

        $response = $this->dispatchDelete(['ids' => [$id], 'mode' => 'permanent']);
        $data = $response->get_data();

        self::assertSame(403, $response->get_status());
        self::assertSame('wbm_forbidden', $data['code']);
        // Product must still exist.
        self::assertNotFalse(wc_get_product($id));
    }

    public function test_permanent_mode_as_admin_hard_deletes(): void
    {
        wp_set_current_user($this->adminUserId);
        $id = $this->createSimpleProduct('HardDelete', 'SKU-HD-1');

        $response = $this->dispatchDelete(['ids' => [$id], 'mode' => 'permanent']);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame('permanent', $data['mode']);
        self::assertSame(1, $data['success']);
        // Clear the WC product cache before re-querying.
        wp_cache_delete($id, 'posts');
        self::assertFalse(wc_get_product($id));
    }

    public function test_permanent_mode_variable_product_cascades_to_variations(): void
    {
        wp_set_current_user($this->adminUserId);
        [$parentId, $variationIds] = $this->createVariableProductWithVariations(2);

        // Sanity: variations exist before.
        foreach ($variationIds as $vid) {
            self::assertNotFalse(wc_get_product($vid));
        }

        $response = $this->dispatchDelete(['ids' => [$parentId], 'mode' => 'permanent']);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $data['success']);

        // Parent gone.
        wp_cache_delete($parentId, 'posts');
        self::assertFalse(wc_get_product($parentId));

        // Variations gone. Use get_post() instead of wc_get_product(): the variation
        // data store's read() returns a hollow WC_Product_Variation object for missing
        // posts instead of throwing, so wc_get_product() is an unreliable "exists" check
        // for variations. Checking wp_posts directly is the source-of-truth assertion.
        foreach ($variationIds as $vid) {
            wp_cache_delete($vid, 'posts');
            self::assertNull(get_post($vid), "Variation {$vid} post should have been removed");
        }
    }

    // --- Input validation ---

    public function test_invalid_mode_returns_400_wbm_invalid_mode(): void
    {
        wp_set_current_user($this->editorUserId);
        $id = $this->createSimpleProduct('ValidationA', 'SKU-VA-1');

        $response = $this->dispatchDelete(['ids' => [$id], 'mode' => 'nuke']);
        $data = $response->get_data();

        // WP may short-circuit schema validation (enum) before our controller
        // even runs. Either way we should get a 400.
        self::assertSame(400, $response->get_status());
        $code = is_array($data) && isset($data['code']) ? $data['code'] : '';
        self::assertTrue(
            in_array($code, ['wbm_invalid_mode', 'rest_invalid_param', 'rest_not_in_enum'], true),
            "Unexpected error code: {$code}"
        );
    }

    public function test_empty_ids_returns_400_wbm_invalid_ids(): void
    {
        wp_set_current_user($this->editorUserId);

        $response = $this->dispatchDelete(['ids' => [], 'mode' => 'trash']);
        $data = $response->get_data();

        self::assertSame(400, $response->get_status());
        $code = is_array($data) && isset($data['code']) ? $data['code'] : '';
        self::assertSame('wbm_invalid_ids', $code);
    }

    public function test_too_many_ids_returns_400_wbm_too_many_ids(): void
    {
        wp_set_current_user($this->editorUserId);

        // 501 positive unique integers.
        $ids = range(1, 501);

        $response = $this->dispatchDelete(['ids' => $ids, 'mode' => 'trash']);
        $data = $response->get_data();

        self::assertSame(400, $response->get_status());
        self::assertSame('wbm_too_many_ids', $data['code'] ?? '');
    }

    public function test_non_existent_ids_yield_error_rows_but_200_status(): void
    {
        wp_set_current_user($this->editorUserId);
        $real = $this->createSimpleProduct('Mixed', 'SKU-MIX-1');
        $bogus = 999999;

        $response = $this->dispatchDelete(['ids' => [$real, $bogus], 'mode' => 'trash']);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(2, $data['total']);
        self::assertSame(1, $data['success']);
        self::assertSame(1, $data['errors']);
        self::assertSame('trash', get_post_status($real));
    }

    // --- Audit log ---

    public function test_audit_log_written_for_successful_trash(): void
    {
        wp_set_current_user($this->editorUserId);
        $id = $this->createSimpleProduct('Audited', 'SKU-AUD-1');

        $response = $this->dispatchDelete(['ids' => [$id], 'mode' => 'trash']);
        self::assertSame(200, $response->get_status());

        $log = $this->changeLog->query([
            'product_id' => $id,
            'field'      => BulkDelete::FIELD_DELETED,
        ]);

        self::assertSame(1, $log['total']);
        self::assertCount(1, $log['items']);

        $entry = $log['items'][0];
        self::assertSame(BulkDelete::FIELD_DELETED, $entry['field']);
        self::assertSame($id, $entry['product_id']);

        self::assertIsArray($entry['new_value']);
        self::assertSame('trash', $entry['new_value']['status'] ?? null);

        self::assertIsArray($entry['old_value']);
        self::assertArrayHasKey('name', $entry['old_value']);
        self::assertArrayHasKey('sku', $entry['old_value']);
        self::assertSame('Audited', $entry['old_value']['name']);
        self::assertSame('SKU-AUD-1', $entry['old_value']['sku']);
    }

    public function test_audit_log_not_written_for_failed_delete(): void
    {
        wp_set_current_user($this->editorUserId);
        $bogus = 888888;

        $response = $this->dispatchDelete(['ids' => [$bogus], 'mode' => 'trash']);
        self::assertSame(200, $response->get_status());

        $log = $this->changeLog->query([
            'product_id' => $bogus,
            'field'      => BulkDelete::FIELD_DELETED,
        ]);

        self::assertSame(0, $log['total']);
        self::assertCount(0, $log['items']);
    }

    // --- Permission ---

    public function test_unauthenticated_user_is_rejected(): void
    {
        wp_set_current_user(0);
        $id = $this->createSimpleProduct('Guarded', 'SKU-G-1');

        $response = $this->dispatchDelete(['ids' => [$id], 'mode' => 'trash']);

        self::assertGreaterThanOrEqual(400, $response->get_status());
        // Product must still exist.
        self::assertSame('publish', get_post_status($id));
    }
}
