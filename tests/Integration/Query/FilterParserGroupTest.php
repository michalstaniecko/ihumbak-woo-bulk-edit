<?php

/**
 * FilterParserGroupTest — AND/OR group logic (Issue #19)
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Query;

use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Query\FilterParser;
use IhumbakWooBulkEdit\Query\QueryBuilder;
use WP_Error;
use WP_UnitTestCase;

final class FilterParserGroupTest extends WP_UnitTestCase
{
    private FilterParser $parser;

    public function set_up(): void
    {
        parent::set_up();
        $registry = new FieldRegistry();
        $this->parser = new FilterParser($registry);

        if (! taxonomy_exists('product_cat')) {
            register_taxonomy('product_cat', 'product', ['hierarchical' => true]);
        }
    }

    // ── Legacy flat-list backward compatibility ────────────────────────────

    public function test_apply_legacy_flat_filters_still_work(): void
    {
        $match = $this->createProduct('Legacy Shirt', 'publish');
        update_post_meta($match, '_sku', 'SHIRT-001');

        $other = $this->createProduct('Other', 'publish');
        update_post_meta($other, '_sku', 'OTHER-001');

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['field' => 'name', 'operator' => 'LIKE', 'value' => 'Legacy'],
        ]);

        self::assertTrue($result);
        $ids = array_column($builder->getResults(), 'id');
        self::assertContains($match, $ids);
        self::assertNotContains($other, $ids);
    }

    // ── AND group semantics ───────────────────────────────────────────────

    public function test_apply_and_group_requires_all_conditions_to_match(): void
    {
        // Only this product matches BOTH conditions.
        $match = $this->createProduct('AND Test Product', 'publish');
        update_post_meta($match, '_regular_price', '25');

        // Matches name but not price.
        $wrongPrice = $this->createProduct('AND Test Other', 'publish');
        update_post_meta($wrongPrice, '_regular_price', '99');

        // Matches price but not name.
        $wrongName = $this->createProduct('Completely Different', 'publish');
        update_post_meta($wrongName, '_regular_price', '25');

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            [
                'type'       => 'group',
                'combinator' => 'AND',
                'children'   => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => 'LIKE', 'value' => 'AND Test'],
                    ['type' => 'condition', 'field' => 'regular_price', 'operator' => '=', 'value' => '25'],
                ],
            ],
        ]);

        self::assertTrue($result);
        $ids = array_column($builder->getResults(), 'id');
        self::assertContains($match, $ids);
        self::assertNotContains($wrongPrice, $ids);
        self::assertNotContains($wrongName, $ids);
    }

    // ── OR group semantics ────────────────────────────────────────────────

    public function test_apply_or_group_matches_any_condition(): void
    {
        $byName  = $this->createProduct('OR Shirt Name', 'publish');
        $byPrice = $this->createProduct('Some Other Product', 'publish');
        update_post_meta($byPrice, '_regular_price', '77');

        $neither = $this->createProduct('No Match At All', 'publish');
        update_post_meta($neither, '_regular_price', '5');

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            [
                'type'       => 'group',
                'combinator' => 'OR',
                'children'   => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => 'LIKE', 'value' => 'OR Shirt'],
                    ['type' => 'condition', 'field' => 'regular_price', 'operator' => '=', 'value' => '77'],
                ],
            ],
        ]);

        self::assertTrue($result);
        $ids = array_column($builder->getResults(), 'id');
        self::assertContains($byName, $ids);
        self::assertContains($byPrice, $ids);
        self::assertNotContains($neither, $ids);
    }

    // ── Nested group semantics ────────────────────────────────────────────

    public function test_apply_nested_group_combines_correctly(): void
    {
        // Logic: (name LIKE 'Nested') AND (price=10 OR price=20)
        $matchPrice10 = $this->createProduct('Nested Item A', 'publish');
        update_post_meta($matchPrice10, '_regular_price', '10');

        $matchPrice20 = $this->createProduct('Nested Item B', 'publish');
        update_post_meta($matchPrice20, '_regular_price', '20');

        $wrongName = $this->createProduct('Different Name', 'publish');
        update_post_meta($wrongName, '_regular_price', '10');

        $wrongPrice = $this->createProduct('Nested Item C', 'publish');
        update_post_meta($wrongPrice, '_regular_price', '99');

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            [
                'type'       => 'group',
                'combinator' => 'AND',
                'children'   => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => 'LIKE', 'value' => 'Nested'],
                    [
                        'type'       => 'group',
                        'combinator' => 'OR',
                        'children'   => [
                            ['type' => 'condition', 'field' => 'regular_price', 'operator' => '=', 'value' => '10'],
                            ['type' => 'condition', 'field' => 'regular_price', 'operator' => '=', 'value' => '20'],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertTrue($result);
        $ids = array_column($builder->getResults(), 'id');
        self::assertContains($matchPrice10, $ids, 'Nested Item A (price 10) should match');
        self::assertContains($matchPrice20, $ids, 'Nested Item B (price 20) should match');
        self::assertNotContains($wrongName, $ids, 'Different Name should not match');
        self::assertNotContains($wrongPrice, $ids, 'Nested Item C (price 99) should not match');
    }

    // ── Top-level FilterGroup (associative, not list-wrapped) ─────────────

    public function test_apply_accepts_top_level_group_associative_array(): void
    {
        // Simulate the REST body shape: associative array (not list-wrapped).
        // This is what PHP decodes when the client sends a bare FilterGroup object.
        $filters = [
            'type'        => 'group',
            'combinator'  => 'AND',
            'children'    => [
                [
                    'type'     => 'condition',
                    'field'    => 'name',
                    'operator' => 'LIKE',
                    'value'    => 'Test',
                ],
            ],
        ];

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, $filters);

        self::assertSame( true, $result, 'FilterParser must accept top-level associative group array' );
    }

    // ── Error cases ───────────────────────────────────────────────────────

    public function test_apply_invalid_combinator_returns_wp_error(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            [
                'type'       => 'group',
                'combinator' => 'XOR',
                'children'   => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'foo'],
                ],
            ],
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_invalid_combinator', $result->get_error_code());
    }

    public function test_apply_depth_exceeded_returns_wp_error(): void
    {
        // Build a chain of groups 6 levels deep (MAX_GROUP_DEPTH = 5).
        $innerCondition = ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'x'];
        $node = $innerCondition;
        for ($i = 0; $i < 6; $i++) {
            $node = ['type' => 'group', 'combinator' => 'AND', 'children' => [$node]];
        }

        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [$node]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_group_depth_exceeded', $result->get_error_code());
    }

    public function test_apply_invalid_filter_node_returns_wp_error(): void
    {
        $builder = new QueryBuilder();
        $result = $this->parser->apply($builder, [
            ['combinator' => 'AND', 'children' => []], // missing 'type'
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_invalid_filter_node', $result->get_error_code());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createProduct(string $title, string $status = 'publish'): int
    {
        return (int) wp_insert_post([
            'post_title'  => $title,
            'post_type'   => 'product',
            'post_status' => $status,
        ]);
    }
}
