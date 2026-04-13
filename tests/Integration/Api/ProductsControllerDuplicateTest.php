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
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Integration tests for POST /products/duplicate (bulk duplicate).
 *
 * Mirrors the structure of {@see ProductsControllerDeleteTest} so the two
 * destructive-batch endpoints share the same test conventions.
 *
 * @license GPL-2.0-or-later
 */
final class ProductsControllerDuplicateTest extends WP_UnitTestCase
{
    private DatabaseMigrator $migrator;
    private ChangeLogRepository $changeLog;
    private int $editorUserId;
    private int $adminUserId;
    private int $subscriberUserId;

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

        // Editor with edit_products — this is the user the duplicate endpoint expects.
        $this->editorUserId = self::factory()->user->create(['role' => 'editor']);
        $editor = get_userdata($this->editorUserId);
        $editor->add_cap('edit_products');

        // Administrator with full WC caps.
        $this->adminUserId = self::factory()->user->create(['role' => 'administrator']);
        $admin = get_userdata($this->adminUserId);
        $admin->add_cap('edit_products');
        $admin->add_cap('delete_products');

        // Subscriber — no edit_products cap, used to verify permission rejection.
        $this->subscriberUserId = self::factory()->user->create(['role' => 'subscriber']);

        // Reset rate-limit transient for the editor between tests so successive
        // calls inside one test don't bleed into the next.
        delete_transient('wbm_rate_batch_duplicate_' . $this->editorUserId);
        delete_transient('wbm_rate_batch_duplicate_' . $this->adminUserId);
    }

    private function createSimpleProduct(string $name = 'Test Product', string $sku = ''): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        if ($sku !== '') {
            $product->set_sku($sku);
        }
        $product->set_regular_price('10');
        $product->set_status('publish');
        return $product->save();
    }

    /**
     * @return array{0: int, 1: list<int>}
     */
    private function createVariableProductWithVariations(int $variationCount = 2): array
    {
        $product = new WC_Product_Variable();
        $product->set_name('Variable Parent');
        $product->set_sku('VAR-DUP-' . uniqid());
        $product->set_status('publish');
        $parentId = $product->save();

        $variationIds = [];
        for ($i = 0; $i < $variationCount; $i++) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parentId);
            $variation->set_regular_price((string) (10 + $i));
            $variationIds[] = $variation->save();
        }

        // Re-read the parent so children are picked up by the data store.
        return [$parentId, $variationIds];
    }

    private function dispatchDuplicate(array $body): WP_REST_Response
    {
        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/products/duplicate');
        $request->set_body_params($body);
        return rest_get_server()->dispatch($request);
    }

    // --- Happy paths ---

    public function test_simple_product_duplicates_with_meta_and_images(): void
    {
        wp_set_current_user($this->editorUserId);
        $sourceId = $this->createSimpleProduct('Original', 'SKU-DUP-1');
        update_post_meta($sourceId, 'vendor', 'acme');

        $response = $this->dispatchDuplicate([
            'ids'         => [$sourceId],
            'copy_meta'   => true,
            'copy_images' => true,
        ]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['success']);
        self::assertSame(0, $data['errors']);
        self::assertCount(1, $data['results']);
        self::assertSame('success', $data['results'][0]['status']);
        self::assertArrayHasKey('new_id', $data['results'][0]);

        $newId = (int) $data['results'][0]['new_id'];
        self::assertGreaterThan(0, $newId);
        self::assertNotSame($sourceId, $newId);

        $duplicate = wc_get_product($newId);
        self::assertNotFalse($duplicate);
        self::assertSame('draft', $duplicate->get_status());
        self::assertNotSame('SKU-DUP-1', $duplicate->get_sku());
        // WC's duplicator appends "(Copy)" to the name.
        self::assertStringContainsString('Original', $duplicate->get_name());
        self::assertStringContainsString('Copy', $duplicate->get_name());

        // Custom (non-underscore) meta is carried over when copy_meta=true.
        self::assertSame('acme', get_post_meta($newId, 'vendor', true));
    }

    public function test_variable_product_duplicates_variations_and_logs_one_parent_entry(): void
    {
        wp_set_current_user($this->editorUserId);
        [$parentId, $variationIds] = $this->createVariableProductWithVariations(2);

        $response = $this->dispatchDuplicate([
            'ids'         => [$parentId],
            'copy_meta'   => true,
            'copy_images' => true,
        ]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(1, $data['success']);

        $newParentId = (int) $data['results'][0]['new_id'];
        self::assertGreaterThan(0, $newParentId);

        $newParent = wc_get_product($newParentId);
        self::assertInstanceOf('WC_Product_Variable', $newParent);
        self::assertSame('draft', $newParent->get_status());

        $newChildren = $newParent->get_children();
        self::assertCount(2, $newChildren);

        // None of the new variation IDs should overlap with the source's.
        foreach ($newChildren as $newChildId) {
            self::assertNotContains($newChildId, $variationIds);
        }

        // Audit log: exactly ONE entry for the parent duplicate, with variations=2.
        $log = $this->changeLog->query([
            'product_id' => $newParentId,
            'field'      => BulkDuplicate::FIELD_DUPLICATED,
        ]);
        self::assertSame(1, $log['total']);
        self::assertCount(1, $log['items']);
        self::assertSame(2, (int) ($log['items'][0]['new_value']['variations'] ?? -1));
        self::assertSame($parentId, (int) ($log['items'][0]['new_value']['source_id'] ?? -1));
    }

    public function test_copy_meta_false_strips_custom_meta_but_preserves_core_meta(): void
    {
        wp_set_current_user($this->editorUserId);
        $sourceId = $this->createSimpleProduct('CoreVsCustom', 'SKU-CORE-1');
        // Add custom (non-underscore) meta — should be stripped.
        update_post_meta($sourceId, 'vendor', 'acme');
        // Underscore-prefixed (treated as core/plugin-managed) — should survive.
        update_post_meta($sourceId, '_my_underscore_meta', 'survives');

        $response = $this->dispatchDuplicate([
            'ids'         => [$sourceId],
            'copy_meta'   => false,
            'copy_images' => true,
        ]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        $newId = (int) $data['results'][0]['new_id'];

        $duplicate = wc_get_product($newId);
        // WC core meta (price, SKU) is preserved by the duplicator regardless
        // of the exclude-meta filter, because they live on the product object,
        // not in get_meta_data().
        self::assertSame('10', $duplicate->get_regular_price());
        self::assertNotSame('', $duplicate->get_sku());

        // Custom non-underscore meta is gone.
        self::assertSame('', get_post_meta($newId, 'vendor', true));

        // Underscore-prefixed meta survives because the impl only excludes
        // non-underscore custom keys when copy_meta=false.
        self::assertSame('survives', get_post_meta($newId, '_my_underscore_meta', true));
    }

    public function test_copy_images_false_clears_thumbnail_and_gallery(): void
    {
        wp_set_current_user($this->editorUserId);
        $sourceId = $this->createSimpleProduct('WithImages', 'SKU-IMG-1');

        // Use bare attachment IDs — no real attachments needed for the assertion.
        $thumbId   = 4242;
        $galleryIds = [101, 102, 103];

        $product = wc_get_product($sourceId);
        $product->set_image_id($thumbId);
        $product->set_gallery_image_ids($galleryIds);
        $product->save();

        $response = $this->dispatchDuplicate([
            'ids'         => [$sourceId],
            'copy_meta'   => true,
            'copy_images' => false,
        ]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        $newId = (int) $data['results'][0]['new_id'];

        $duplicate = wc_get_product($newId);
        self::assertSame(0, (int) $duplicate->get_image_id());
        self::assertSame([], $duplicate->get_gallery_image_ids());
    }

    public function test_copy_images_true_shares_attachment_references(): void
    {
        wp_set_current_user($this->editorUserId);
        $sourceId = $this->createSimpleProduct('SharedImages', 'SKU-IMG-2');

        $thumbId = 9999;
        $product = wc_get_product($sourceId);
        $product->set_image_id($thumbId);
        $product->save();

        $response = $this->dispatchDuplicate([
            'ids'         => [$sourceId],
            'copy_meta'   => true,
            'copy_images' => true,
        ]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        $newId = (int) $data['results'][0]['new_id'];

        $duplicate = wc_get_product($newId);
        self::assertSame($thumbId, (int) $duplicate->get_image_id());
    }

    public function test_duplicate_status_is_always_draft_even_for_published_source(): void
    {
        wp_set_current_user($this->editorUserId);
        $sourceId = $this->createSimpleProduct('PublishedSource', 'SKU-PUB-1');

        // Sanity: source is published.
        self::assertSame('publish', get_post_status($sourceId));

        $response = $this->dispatchDuplicate(['ids' => [$sourceId]]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        $newId = (int) $data['results'][0]['new_id'];

        self::assertSame('draft', get_post_status($newId));
        self::assertSame('publish', get_post_status($sourceId));
    }

    public function test_always_excluded_meta_is_dropped(): void
    {
        wp_set_current_user($this->editorUserId);
        $sourceId = $this->createSimpleProduct('SalesData', 'SKU-SALES-1');

        $product = wc_get_product($sourceId);
        $product->set_total_sales(100);
        $product->save();

        // Sanity: source has total_sales set.
        self::assertSame(100, $product->get_total_sales());

        $response = $this->dispatchDuplicate([
            'ids'       => [$sourceId],
            'copy_meta' => true,
        ]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        $newId = (int) $data['results'][0]['new_id'];

        $duplicate = wc_get_product($newId);
        // WC core's duplicator forces total_sales to 0; ALWAYS_EXCLUDED_META also lists
        // it for safety. Either way, the duplicate must not inherit the source's sales.
        self::assertSame(0, $duplicate->get_total_sales());
    }

    public function test_audit_log_written_for_each_duplicate(): void
    {
        wp_set_current_user($this->editorUserId);
        $sourceA = $this->createSimpleProduct('AuditA', 'SKU-AUD-A');
        $sourceB = $this->createSimpleProduct('AuditB', 'SKU-AUD-B');

        $response = $this->dispatchDuplicate(['ids' => [$sourceA, $sourceB]]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(2, $data['success']);

        $newA = (int) $data['results'][0]['new_id'];
        $newB = (int) $data['results'][1]['new_id'];

        $log = $this->changeLog->query([
            'field' => BulkDuplicate::FIELD_DUPLICATED,
        ]);
        self::assertSame(2, $log['total']);

        // Build a map keyed by NEW product_id so the assertion is order-independent.
        $byNew = [];
        foreach ($log['items'] as $entry) {
            $byNew[(int) $entry['product_id']] = $entry;
        }

        self::assertArrayHasKey($newA, $byNew);
        self::assertArrayHasKey($newB, $byNew);

        self::assertSame($sourceA, (int) $byNew[$newA]['new_value']['source_id']);
        self::assertSame('draft', $byNew[$newA]['new_value']['status']);
        self::assertSame('AuditA', $byNew[$newA]['new_value']['source_name']);

        self::assertSame($sourceB, (int) $byNew[$newB]['new_value']['source_id']);
        self::assertSame('draft', $byNew[$newB]['new_value']['status']);
        self::assertSame('AuditB', $byNew[$newB]['new_value']['source_name']);
    }

    // --- Input validation ---

    public function test_empty_ids_returns_400_wbm_invalid_ids(): void
    {
        wp_set_current_user($this->editorUserId);

        $response = $this->dispatchDuplicate(['ids' => []]);
        $data = $response->get_data();

        self::assertSame(400, $response->get_status());
        $code = is_array($data) && isset($data['code']) ? $data['code'] : '';
        // WP REST may short-circuit on the schema's `minItems` constraint
        // before our controller runs — accept either path to a 400.
        self::assertTrue(
            in_array($code, ['wbm_invalid_ids', 'rest_invalid_param', 'rest_too_few_items'], true),
            "Unexpected error code: {$code}"
        );
    }

    public function test_non_existent_ids_yield_error_rows_but_200_status(): void
    {
        wp_set_current_user($this->editorUserId);
        $real = $this->createSimpleProduct('Mixed', 'SKU-MIX-1');
        $bogus = 999999;

        $response = $this->dispatchDuplicate(['ids' => [$real, $bogus]]);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame(2, $data['total']);
        self::assertSame(1, $data['success']);
        self::assertSame(1, $data['errors']);

        // Find the error row (its `id` matches the bogus source id).
        $errorRow = null;
        foreach ($data['results'] as $row) {
            if ($row['status'] === 'error') {
                $errorRow = $row;
                break;
            }
        }
        self::assertNotNull($errorRow);
        self::assertSame($bogus, (int) $errorRow['id']);
        self::assertSame('wbm_not_found', $errorRow['code'] ?? '');
    }

    public function test_too_many_ids_returns_400_wbm_too_many_ids(): void
    {
        wp_set_current_user($this->editorUserId);

        // 101 positive unique integers — controller cap is 100.
        $ids = range(1, 101);

        $response = $this->dispatchDuplicate(['ids' => $ids]);
        $data = $response->get_data();

        self::assertSame(400, $response->get_status());
        $code = is_array($data) && isset($data['code']) ? $data['code'] : '';
        // WP schema may catch maxItems before our controller — accept either.
        self::assertTrue(
            in_array($code, ['wbm_too_many_ids', 'rest_invalid_param', 'rest_too_many_items'], true),
            "Unexpected error code: {$code}"
        );
    }

    // --- Permissions ---

    public function test_unauthenticated_user_is_rejected(): void
    {
        wp_set_current_user(0);
        $sourceId = $this->createSimpleProduct('Guarded', 'SKU-GD-1');

        $response = $this->dispatchDuplicate(['ids' => [$sourceId]]);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    public function test_user_without_edit_products_is_forbidden(): void
    {
        wp_set_current_user($this->subscriberUserId);
        $sourceId = $this->createSimpleProduct('NoCap', 'SKU-NC-1');

        $response = $this->dispatchDuplicate(['ids' => [$sourceId]]);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    // --- Rate limiting ---

    public function test_rate_limited_after_ten_calls_in_a_minute(): void
    {
        wp_set_current_user($this->editorUserId);
        delete_transient('wbm_rate_batch_duplicate_' . $this->editorUserId);

        $sourceId = $this->createSimpleProduct('RateLimited', 'SKU-RL-1');

        // Burn through the 10-request window.
        for ($i = 0; $i < 10; $i++) {
            $response = $this->dispatchDuplicate(['ids' => [$sourceId]]);
            self::assertSame(200, $response->get_status(), "Call #{$i} should have succeeded");
        }

        // 11th call should be rate-limited.
        $response = $this->dispatchDuplicate(['ids' => [$sourceId]]);
        self::assertSame(429, $response->get_status());
        $data = $response->get_data();
        self::assertSame('wbm_rate_limit_exceeded', $data['code'] ?? '');
    }
}
