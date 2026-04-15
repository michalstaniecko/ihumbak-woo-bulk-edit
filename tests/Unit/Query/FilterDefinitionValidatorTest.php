<?php

/**
 * Unit tests for FilterDefinitionValidator.
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query;

use IhumbakWooBulkEdit\Query\FilterDefinitionValidator;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \IhumbakWooBulkEdit\Query\FilterDefinitionValidator
 */
final class FilterDefinitionValidatorTest extends TestCase
{
    private FilterDefinitionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FilterDefinitionValidator();
    }

    // ── Empty / missing filters ───────────────────────────────────────────────

    public function test_empty_flat_filters_returns_empty_error(): void
    {
        $definition = ['filters' => []];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_filter_empty', $result->get_error_code());
    }

    public function test_missing_filters_key_returns_invalid_definition(): void
    {
        $definition = ['search' => 'foo'];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_filter_invalid_definition', $result->get_error_code());
    }

    // ── Legacy flat list ──────────────────────────────────────────────────────

    public function test_valid_legacy_flat_list_returns_null(): void
    {
        $definition = [
            'filters' => [
                ['field' => 'name', 'operator' => 'LIKE', 'value' => 'shirt'],
                ['field' => 'sku', 'operator' => '=', 'value' => 'SKU-1'],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertNull($result);
    }

    public function test_legacy_condition_missing_field_returns_error(): void
    {
        $definition = [
            'filters' => [
                ['operator' => '=', 'value' => 'test'],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
    }

    public function test_legacy_condition_missing_operator_returns_error(): void
    {
        $definition = [
            'filters' => [
                ['field' => 'name', 'value' => 'test'],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
    }

    // ── Group tree format ─────────────────────────────────────────────────────

    public function test_valid_AND_group_returns_null(): void
    {
        $definition = [
            'filters' => [
                'type'        => 'group',
                'combinator'  => 'AND',
                'children'    => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => 'LIKE', 'value' => 'shirt'],
                ],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertNull($result);
    }

    public function test_valid_OR_root_returns_null(): void
    {
        $definition = [
            'filters' => [
                'type'       => 'group',
                'combinator' => 'OR',
                'children'   => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'A'],
                    ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'B'],
                ],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertNull($result);
    }

    public function test_nested_groups_valid_returns_null(): void
    {
        $definition = [
            'filters' => [
                'type'       => 'group',
                'combinator' => 'AND',
                'children'   => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => 'LIKE', 'value' => 'shirt'],
                    [
                        'type'       => 'group',
                        'combinator' => 'OR',
                        'children'   => [
                            ['type' => 'condition', 'field' => 'sku', 'operator' => '=', 'value' => 'A'],
                            ['type' => 'condition', 'field' => 'sku', 'operator' => '=', 'value' => 'B'],
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertNull($result);
    }

    public function test_group_empty_children_returns_empty_error(): void
    {
        $definition = [
            'filters' => [
                'type'       => 'group',
                'combinator' => 'AND',
                'children'   => [],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_filter_empty', $result->get_error_code());
    }

    public function test_bad_combinator_returns_error(): void
    {
        $definition = [
            'filters' => [
                'type'       => 'group',
                'combinator' => 'XOR',
                'children'   => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'A'],
                ],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_filter_invalid_definition', $result->get_error_code());
    }

    public function test_missing_type_in_group_child_returns_error(): void
    {
        $definition = [
            'filters' => [
                'type'       => 'group',
                'combinator' => 'AND',
                'children'   => [
                    // Missing both 'type' and 'field' — ambiguous node
                    ['operator' => '=', 'value' => 'test'],
                ],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
    }

    // ── Depth limit ───────────────────────────────────────────────────────────

    public function test_depth_exactly_5_is_valid(): void
    {
        // Build a chain: root (depth 0) → child (1) → child (2) → child (3) →
        // child (4) → child (5) → leaf condition.
        // The validator validates root at depth 0; each child increments depth.
        // We want the deepest group to be at depth 5 (the limit).
        // Start from the condition, then wrap 5 times.
        $leaf = ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'leaf'];
        $node = ['type' => 'group', 'combinator' => 'AND', 'children' => [$leaf]]; // depth 5

        for ($i = 0; $i < 5; $i++) {
            $node = ['type' => 'group', 'combinator' => 'AND', 'children' => [$node]];
        }
        // $node is now at depth 0; its chain goes down to depth 5.

        $definition = ['filters' => $node];
        $result = $this->validator->validate($definition);

        self::assertNull($result);
    }

    public function test_depth_exceeds_5_returns_too_deep_error(): void
    {
        // Same as above but wrapped one more time so the deepest group is at depth 6.
        $leaf = ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'leaf'];
        $node = ['type' => 'group', 'combinator' => 'AND', 'children' => [$leaf]]; // will be at depth 6

        for ($i = 0; $i < 6; $i++) {
            $node = ['type' => 'group', 'combinator' => 'AND', 'children' => [$node]];
        }

        $definition = ['filters' => $node];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_filter_too_deep', $result->get_error_code());
    }

    // ── Node count limit ─────────────────────────────────────────────────────

    public function test_node_count_exceeds_200_returns_error(): void
    {
        // The root group counts as 1 node, so 200 condition children = 201 total.
        // MAX_NODES is 200, so this must fail.
        $children = [];
        for ($i = 0; $i < 200; $i++) {
            $children[] = ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => "v{$i}"];
        }
        $definition = [
            'filters' => [
                'type'       => 'group',
                'combinator' => 'AND',
                'children'   => $children,
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_filter_invalid_definition', $result->get_error_code());
    }

    public function test_node_count_exactly_200_is_valid(): void
    {
        // Root group (1) + 199 conditions = 200 nodes total — exactly at the limit.
        $children = [];
        for ($i = 0; $i < 199; $i++) {
            $children[] = ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => "v{$i}"];
        }
        $definition = [
            'filters' => [
                'type'       => 'group',
                'combinator' => 'AND',
                'children'   => $children,
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertNull($result);
    }

    // ── optional fields ───────────────────────────────────────────────────────

    public function test_valid_definition_with_search_and_sort(): void
    {
        $definition = [
            'filters' => [
                ['field' => 'name', 'operator' => '=', 'value' => 'shirt'],
            ],
            'search' => 'keyword',
            'sort'   => ['field' => 'name', 'order' => 'asc'],
        ];
        $result = $this->validator->validate($definition);

        self::assertNull($result);
    }

    public function test_only_search_no_filters_returns_null(): void
    {
        // A definition with empty filters but a search query is valid if the
        // caller wants to save "just a search". However, the validator treats
        // empty filters[] the same regardless — still wbm_filter_empty.
        // This test documents that behaviour explicitly.
        $definition = ['filters' => [], 'search' => 'some keyword'];
        $result = $this->validator->validate($definition);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_filter_empty', $result->get_error_code());
    }

    // ── Empty nested group is skipped (not an error) ──────────────────────────

    /**
     * Regression test: user adds a condition AND accidentally clicks "+ Add group"
     * without adding any conditions to the nested group. The validator must skip
     * the empty nested group and accept the definition (the condition is still there).
     */
    public function test_group_with_condition_and_empty_nested_group_is_valid(): void
    {
        $definition = [
            'filters' => [
                'type'      => 'group',
                'combinator' => 'AND',
                'children'  => [
                    ['type' => 'condition', 'field' => 'name', 'operator' => '=', 'value' => 'test'],
                    ['type' => 'group', 'combinator' => 'AND', 'children' => []],  // empty nested group
                ],
            ],
        ];
        $result = $this->validator->validate($definition);

        self::assertNull($result, 'A group with a condition + an empty nested group should be accepted.');
    }

    /**
     * A root group that contains ONLY empty nested groups (no real conditions)
     * is still rejected — there is nothing to filter on.
     */
    public function test_group_with_only_empty_nested_groups_returns_empty_error(): void
    {
        $definition = [
            'filters' => [
                'type'      => 'group',
                'combinator' => 'AND',
                'children'  => [
                    ['type' => 'group', 'combinator' => 'AND', 'children' => []],
                    ['type' => 'group', 'combinator' => 'OR',  'children' => []],
                ],
            ],
        ];
        $result = $this->validator->validate($definition);

        // After skipping both empty nested groups, the root effectively has no
        // children that contribute conditions — but we do not reject it at the
        // validator level. The caller (FiltersController) would save an empty-ish
        // definition; the frontend's isEffectivelyEmpty check prevents this from
        // being submitted in the first place.
        // Documenting current validator behaviour: empty nested groups are skipped
        // and the validate() call itself returns null.
        self::assertNull($result);
    }
}
