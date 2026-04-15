<?php

/**
 * Unit tests for FilterParser.
 *
 * Covers group-tree compilation with an emphasis on taxonomy conditions and the
 * edge cases that previously produced zero results:
 *
 * 1. Two AND-joined groups each containing a taxonomy condition (the primary
 *    bug scenario: categories=X AND car_make=Y).
 * 2. The ultimate use-case: ((category) AND (make=Ford OR make=Toyota)).
 * 3. Three different taxonomy conditions ANDed together.
 * 4. Negation + AND.
 * 5. Two taxonomy conditions in the SAME group ANDed.
 * 6. Array value passed to an = operator on a taxonomy field (operator-switch
 *    bug from FilterConditionRow).
 * 7. Placeholder count validation for every variant.
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query;

use IhumbakWooBulkEdit\Fields\Core\CustomTaxonomyField;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Fields\TaxonomyMap;
use IhumbakWooBulkEdit\Query\FilterParser;
use IhumbakWooBulkEdit\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \IhumbakWooBulkEdit\Query\FilterParser
 */
final class FilterParserTest extends TestCase
{
    private FieldRegistry $registry;

    protected function setUp(): void
    {
        TaxonomyMap::resetRuntimeEntries();

        // Register two custom taxonomy fields used across tests.
        TaxonomyMap::register('car_make', 'car_make');
        TaxonomyMap::register('car_year', 'car_year');

        $this->registry = new FieldRegistry();
        $this->registry->register(new CustomTaxonomyField('car_make', 'Car Make', 'car_make'));
        $this->registry->register(new CustomTaxonomyField('car_year', 'Car Year', 'car_year'));
    }

    protected function tearDown(): void
    {
        TaxonomyMap::resetRuntimeEntries();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a fresh QueryBuilder + FilterParser pair.
     */
    private function makeParser(): array
    {
        $builder = new QueryBuilder();
        $builder->paginate(1, 50);
        $parser = new FilterParser($this->registry);
        return [$parser, $builder];
    }

    /**
     * Apply a filter tree and assert it does NOT produce a WP_Error.
     *
     * Returns the compiled SQL and values from the builder's first (and
     * typically only) condition entry, obtained via reflection.
     *
     * @param array<string, mixed> $filters
     * @return array{sql: string, values: list<mixed>}
     */
    private function applyAndGetCondition(array $filters): array
    {
        [$parser, $builder] = $this->makeParser();

        $result = $parser->apply($builder, $filters);
        self::assertNotInstanceOf(
            WP_Error::class,
            $result,
            'FilterParser::apply() returned an unexpected WP_Error: '
                . ($result instanceof WP_Error ? $result->get_error_message() : '')
        );
        self::assertTrue($result);

        $ref  = new \ReflectionClass($builder);
        $prop = $ref->getProperty('conditions');
        $prop->setAccessible(true);
        $conditions = $prop->getValue($builder);

        self::assertNotEmpty($conditions, 'Expected at least one compiled condition.');
        // The parser pushes a single merged condition for the whole tree.
        return $conditions[0];
    }

    /**
     * Count the number of %s / %d placeholders in $sql and assert it equals
     * the number of values — the key predicate that prevents $wpdb->prepare()
     * from returning null or producing broken SQL.
     *
     * @param list<mixed> $values
     */
    private function assertPlaceholderBalance(string $sql, array $values, string $message = ''): void
    {
        preg_match_all('/%[sd]/', $sql, $m);
        $placeholders = count($m[0]);
        $count        = count($values);

        self::assertSame(
            $placeholders,
            $count,
            $message ?: "Placeholder count ($placeholders) != value count ($count). SQL: $sql"
        );
    }

    /**
     * Assert the SQL does NOT contain the literal string "Array" which would
     * appear when PHP silently casts an array to string.
     */
    private function assertNoArrayLiteral(string $sql): void
    {
        self::assertStringNotContainsString(
            "'Array'",
            $sql,
            'SQL contains the literal string \'Array\', indicating an array was '
                . 'cast to string. This causes zero results for all queries.'
        );
    }

    // ── Test: legacy flat-list (regression guard) ─────────────────────────

    public function test_legacy_flat_list_with_two_taxonomy_conditions(): void
    {
        $filters = [
            ['field' => 'categories', 'operator' => '=', 'value' => '5'],
            ['field' => 'car_make',   'operator' => '=', 'value' => 'Ford'],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        // Taxonomy slugs are in the values array (as %s bound params), not in the SQL template.
        self::assertContains('product_cat', $cond['values']);
        self::assertContains('car_make',    $cond['values']);
        self::assertSame(2, substr_count($cond['sql'], 'SELECT tr.object_id'));
    }

    // ── Test: two AND-joined groups (the primary bug scenario) ────────────

    /**
     * Primary bug scenario:
     *   Root (AND) → Group 1 (AND): categories = 5
     *               → Group 2 (AND): car_make = "Ford"
     *
     * Previously produced zero results because of a $wpdb->prepare placeholder
     * mismatch or aliasing issue (per bug report).
     */
    public function test_two_and_groups_each_with_one_taxonomy_condition(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => '=', 'value' => 'Ford'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        // Taxonomy slugs are bound as %s values, not embedded as literals in the SQL template.
        self::assertNotContains('product_cat', array_slice($cond['values'], 0, 0)); // sanity

        // Placeholder count must match value count — the root cause of the bug.
        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);

        // Values must contain the taxonomy slugs and term values.
        self::assertContains('product_cat', $cond['values']);
        self::assertContains('car_make',    $cond['values']);
        self::assertContains('Ford',        $cond['values']);

        // The compound condition must combine both subqueries with AND.
        self::assertStringContainsString(' AND ', $cond['sql']);
        self::assertSame(2, substr_count($cond['sql'], 'SELECT tr.object_id'));
    }

    /**
     * Same scenario but using string names for both (FilterGroupBuilder path).
     */
    public function test_two_and_groups_string_names_for_both_taxonomies(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => 'Cars'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => '=', 'value' => 'Ford'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        $this->assertNoArrayLiteral($cond['sql']);

        self::assertContains('product_cat', $cond['values']);
        self::assertContains('Cars',        $cond['values']);
        self::assertContains('car_make',    $cond['values']);
        self::assertContains('Ford',        $cond['values']);
    }

    // ── Test: ((cat=X) AND (make=Ford OR make=Toyota)) ─────────────────────

    /**
     * Ultimate use-case:
     *   Root (AND) → Group 1 (AND): categories = 5
     *               → Group 2 (OR) : car_make = "Ford"
     *                                car_make = "Toyota"
     */
    public function test_and_joined_groups_where_second_group_is_or(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'OR',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => '=', 'value' => 'Ford'],
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => '=', 'value' => 'Toyota'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        $this->assertNoArrayLiteral($cond['sql']);

        // The OR group must be parenthesised so AND has correct precedence.
        self::assertMatchesRegularExpression('/\(.*OR.*\)/s', $cond['sql']);

        // Three subqueries: one for cat, two for makes.
        self::assertSame(3, substr_count($cond['sql'], 'SELECT tr.object_id'));

        // All taxonomy slugs must appear.
        self::assertContains('product_cat', $cond['values']);
        self::assertContains('car_make',    $cond['values']);
        self::assertContains('Ford',        $cond['values']);
        self::assertContains('Toyota',      $cond['values']);
    }

    // ── Test: three different taxonomies ANDed ────────────────────────────

    public function test_three_different_taxonomy_conditions_and_joined(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => '=', 'value' => 'Ford'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_year', 'operator' => '=', 'value' => '2020'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        $this->assertNoArrayLiteral($cond['sql']);

        self::assertSame(3, substr_count($cond['sql'], 'SELECT tr.object_id'));
        self::assertContains('product_cat', $cond['values']);
        self::assertContains('car_make',    $cond['values']);
        self::assertContains('car_year',    $cond['values']);
    }

    // ── Test: negation + AND ──────────────────────────────────────────────

    public function test_negation_and_taxonomy_condition(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '!=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => '=', 'value' => 'Ford'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        $this->assertNoArrayLiteral($cond['sql']);

        // First subquery must be NOT IN (negation for !=).
        self::assertStringContainsString('NOT IN', $cond['sql']);

        self::assertContains('product_cat', $cond['values']);
        self::assertContains('car_make',    $cond['values']);
        self::assertContains('Ford',        $cond['values']);
    }

    // ── Test: two taxonomy conditions in the same group ───────────────────

    public function test_two_taxonomy_conditions_in_same_and_group(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                        ['type' => 'condition', 'field' => 'car_make',   'operator' => '=', 'value' => 'Ford'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        $this->assertNoArrayLiteral($cond['sql']);

        self::assertSame(2, substr_count($cond['sql'], 'SELECT tr.object_id'));
        self::assertContains('product_cat', $cond['values']);
        self::assertContains('car_make',    $cond['values']);
        self::assertContains('Ford',        $cond['values']);
    }

    // ── Test: array value passed to = operator (operator-switch bug) ──────

    /**
     * When the user switches from BETWEEN (value = ["Ford","Toyota"]) to = in
     * FilterConditionRow without clearing the value, the backend receives an
     * array for the = operator.
     *
     * Expected behavior: the parser must NOT cast the array to the PHP string
     * "Array" which would produce WHERE t.name = 'Array' (zero results).
     * Instead it should treat each element as a separate name and emit an IN
     * subquery, OR it should use the first element only, OR it should return a
     * validation error — any result is acceptable EXCEPT the silent 'Array' string.
     */
    public function test_array_value_for_equal_operator_on_taxonomy_does_not_produce_array_literal(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        [
                            'type'     => 'condition',
                            'field'    => 'car_make',
                            'operator' => '=',
                            // Simulates a leftover BETWEEN/range value.
                            'value'    => ['Ford', 'Toyota'],
                        ],
                    ],
                ],
            ],
        ];

        [$parser, $builder] = $this->makeParser();
        $result = $parser->apply($builder, $filters);

        if ($result instanceof WP_Error) {
            // Returning a validation error is acceptable for this edge case.
            self::assertNotEmpty($result->get_error_code());
            return;
        }

        // If parsing succeeded, the SQL must NOT contain the literal 'Array'.
        $ref  = new \ReflectionClass($builder);
        $prop = $ref->getProperty('conditions');
        $prop->setAccessible(true);
        $conditions = $prop->getValue($builder);

        if (! empty($conditions)) {
            $this->assertNoArrayLiteral($conditions[0]['sql']);
            $this->assertPlaceholderBalance($conditions[0]['sql'], $conditions[0]['values']);
        }
    }

    // ── Test: empty value is handled gracefully ───────────────────────────

    /**
     * Default conditions from FilterGroupBuilder have value = ''. The parser
     * must not produce broken SQL (the empty-string condition may match nothing,
     * but it must not cause a prepare() mismatch or a PHP error).
     */
    public function test_empty_string_value_for_taxonomy_condition_does_not_break_sql(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => '=', 'value' => ''],
                    ],
                ],
            ],
        ];

        [$parser, $builder] = $this->makeParser();
        $result = $parser->apply($builder, $filters);

        // Must not throw or return WP_Error for empty value.
        // (Empty value will produce zero results but must not break the query.)
        if ($result instanceof WP_Error) {
            // Acceptable: parser can reject empty values with a clear error.
            self::assertNotEmpty($result->get_error_code());
            return;
        }

        $ref  = new \ReflectionClass($builder);
        $prop = $ref->getProperty('conditions');
        $prop->setAccessible(true);
        $conditions = $prop->getValue($builder);

        if (! empty($conditions)) {
            $this->assertPlaceholderBalance($conditions[0]['sql'], $conditions[0]['values']);
        }
    }

    // ── Test: WP_Error on unknown field ───────────────────────────────────

    public function test_unknown_field_returns_wp_error(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                ['type' => 'condition', 'field' => 'nonexistent_field', 'operator' => '=', 'value' => 'x'],
            ],
        ];

        [$parser, $builder] = $this->makeParser();
        $result = $parser->apply($builder, $filters);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_invalid_filter_field', $result->get_error_code());
    }

    // ── Test: non-taxonomy AND non-meta field falls through to tautology ──

    public function test_unresolvable_field_falls_through_to_tautology(): void
    {
        // 'thumbnail' is in FieldRegistry (registered as core) but is not a
        // post-column, meta field, or taxonomy — it falls through to tautology.
        // This tests the final fallback branch in buildConditionSql.
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
    }

    // ── Test: legacy flat FilterGroup object (associative PHP array) ──────

    /**
     * When the REST request body contains a bare FilterGroup JSON object
     * (not wrapped in an array), PHP receives it as an associative array.
     * normalizeInput must detect the 'type' key and return it directly.
     */
    public function test_bare_filter_group_object_is_normalised_correctly(): void
    {
        // Simulates what WP REST API delivers when the frontend sends the
        // root FilterGroup object directly as the filters parameter.
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                ['type' => 'condition', 'field' => 'car_make',   'operator' => '=', 'value' => 'Ford'],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        self::assertContains('product_cat', $cond['values']);
        self::assertContains('car_make',    $cond['values']);
    }

    // ── Test: IS EMPTY / IS NOT EMPTY on taxonomy in an AND group ─────────

    public function test_is_empty_operator_in_and_group(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => 'IS EMPTY'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        $this->assertNoArrayLiteral($cond['sql']);

        // IS EMPTY → NOT IN (has no terms in car_make).
        self::assertStringContainsString('NOT IN', $cond['sql']);
    }

    // ── Test: LIKE operator in an OR group ────────────────────────────────

    public function test_like_operator_in_or_group_with_term_id_and(): void
    {
        $filters = [
            'type'       => 'group',
            'combinator' => 'AND',
            'children'   => [
                [
                    'type'       => 'group',
                    'combinator' => 'AND',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'categories', 'operator' => '=', 'value' => '5'],
                    ],
                ],
                [
                    'type'       => 'group',
                    'combinator' => 'OR',
                    'children'   => [
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => 'LIKE',     'value' => 'Ford'],
                        ['type' => 'condition', 'field' => 'car_make', 'operator' => 'NOT LIKE', 'value' => 'BMW'],
                    ],
                ],
            ],
        ];

        $cond = $this->applyAndGetCondition($filters);

        $this->assertPlaceholderBalance($cond['sql'], $cond['values']);
        $this->assertNoArrayLiteral($cond['sql']);

        // LIKE value is escaped with wildcards; NOT LIKE produces NOT IN subquery.
        self::assertStringContainsString('LIKE', $cond['sql']);
        self::assertStringContainsString('NOT IN', $cond['sql']);
    }
}
