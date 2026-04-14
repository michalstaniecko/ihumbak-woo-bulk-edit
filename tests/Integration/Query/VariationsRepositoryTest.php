<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Query;

use IhumbakWooBulkEdit\Query\VariationsRepository;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_UnitTestCase;

/**
 * Integration tests for VariationsRepository.
 */
final class VariationsRepositoryTest extends WP_UnitTestCase
{
    private VariationsRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new VariationsRepository();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Create a variable product with the given number of variations.
     *
     * @return array{parent_id: int, variation_ids: list<int>}
     */
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
            $variation->set_sku('VAR-' . $parentId . '-' . $i);
            $variation->set_status('publish');
            $variation->set_menu_order($i);
            $variationIds[] = $variation->save();
        }

        return ['parent_id' => $parentId, 'variation_ids' => $variationIds];
    }

    // ── fetchByParent ─────────────────────────────────────────────────────────

    public function test_fetch_by_parent_returns_variations_in_menu_order(): void
    {
        $result = $this->createVariableProduct(3);
        $parentId = $result['parent_id'];

        $variations = $this->repo->fetchByParent($parentId);

        self::assertCount(3, $variations);

        // Verify sorted by menu_order ascending.
        $orders = array_column($variations, 'menu_order');
        $sorted = $orders;
        sort($sorted);
        self::assertSame($sorted, $orders);
    }

    public function test_fetch_by_parent_returns_expected_shape(): void
    {
        $result = $this->createVariableProduct(1);
        $parentId = $result['parent_id'];
        $variationId = $result['variation_ids'][0];

        $variations = $this->repo->fetchByParent($parentId);

        self::assertCount(1, $variations);
        $v = $variations[0];

        // Required keys in variation response.
        foreach ([
            'id', 'parent_id', 'name', 'sku', 'regular_price', 'sale_price',
            'stock_quantity', 'manage_stock', 'weight', 'length', 'width', 'height',
            'thumbnail_id', 'status', 'menu_order', 'attributes', 'post_modified',
        ] as $key) {
            self::assertArrayHasKey($key, $v, "Variation response missing key: {$key}");
        }

        self::assertSame($variationId, $v['id']);
        self::assertSame($parentId, $v['parent_id']);
        self::assertSame('VAR-' . $parentId . '-0', $v['sku']);
    }

    public function test_fetch_by_parent_returns_empty_array_for_simple_product(): void
    {
        $simple = new WC_Product_Simple();
        $simple->set_name('Simple');
        $simpleId = $simple->save();

        $variations = $this->repo->fetchByParent($simpleId);

        self::assertSame([], $variations);
    }

    public function test_fetch_by_parent_returns_empty_array_for_nonexistent_parent(): void
    {
        $variations = $this->repo->fetchByParent(999999999);

        self::assertSame([], $variations);
    }

    public function test_fetch_by_parent_sale_price_populated_when_set(): void
    {
        $result = $this->createVariableProduct(0);
        $parentId = $result['parent_id'];

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parentId);
        $variation->set_regular_price('20.00');
        $variation->set_sale_price('15.00');
        $variation->set_status('publish');
        $variation->save();

        $variations = $this->repo->fetchByParent($parentId);

        self::assertCount(1, $variations);
        self::assertSame('15.00', $variations[0]['sale_price']);
    }

    // ── countsByParents ───────────────────────────────────────────────────────

    public function test_counts_by_parents_returns_correct_counts(): void
    {
        $r1 = $this->createVariableProduct(3);
        $r2 = $this->createVariableProduct(1);

        $counts = $this->repo->countsByParents([$r1['parent_id'], $r2['parent_id']]);

        self::assertSame(3, $counts[$r1['parent_id']]);
        self::assertSame(1, $counts[$r2['parent_id']]);
    }

    public function test_counts_by_parents_returns_zero_for_simple_products(): void
    {
        $simple = new WC_Product_Simple();
        $simple->set_name('Simple');
        $simpleId = $simple->save();

        $counts = $this->repo->countsByParents([$simpleId]);

        // Simple product has no variations — key may be absent or 0.
        $count = $counts[$simpleId] ?? 0;
        self::assertSame(0, $count);
    }

    public function test_counts_by_parents_returns_empty_for_empty_input(): void
    {
        $counts = $this->repo->countsByParents([]);

        self::assertSame([], $counts);
    }
}
