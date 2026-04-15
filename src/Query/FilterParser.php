<?php

/**
 * FilterParser — parses filter JSON and applies conditions to QueryBuilder.
 *
 * Supports both the legacy flat-list format and the new AND/OR group tree
 * format introduced in Issue #19.
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query;

use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Query\Operators\OperatorInterface;
use IhumbakWooBulkEdit\Query\Operators\OperatorRegistry;
use WP_Error;

/**
 * Parses filter input from the REST API and applies conditions to QueryBuilder.
 *
 * Supported input formats:
 *
 * Legacy flat list (all joined with AND, same as before):
 * [
 *   { "field": "name", "operator": "LIKE", "value": "shirt" },
 *   { "field": "regular_price", "operator": ">", "value": "10" }
 * ]
 *
 * New group tree format (Issue #19):
 * [
 *   {
 *     "type": "group",
 *     "combinator": "AND"|"OR",
 *     "children": [
 *       { "type": "condition", "field": "name", "operator": "LIKE", "value": "shirt" },
 *       { "type": "group", "combinator": "OR", "children": [ ... ] }
 *     ]
 *   }
 * ]
 *
 * A condition node can also omit "type" when embedded in a group's children —
 * the presence of a "field" key makes it unambiguous. The legacy flat-list
 * check wraps the entire input in an implicit top-level AND group.
 */
final class FilterParser
{
    /**
     * Maximum allowed nesting depth for filter groups.
     * A depth of 0 means the root group. Depth 5 = 6 group levels total.
     */
    private const MAX_GROUP_DEPTH = 5;

    private OperatorRegistry $operators;

    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
    ) {
        $this->operators = new OperatorRegistry();
    }

    /**
     * Apply filter input to a QueryBuilder.
     *
     * Accepts both the legacy flat-list format and the new group tree format.
     *
     * @param list<array<string, mixed>> $filters Raw filter input from the REST request.
     */
    public function apply(QueryBuilder $builder, array $filters): true|WP_Error
    {
        if (empty($filters)) {
            return true;
        }

        // Normalize to a single root group.
        $root = $this->normalizeInput($filters);

        if ($root instanceof WP_Error) {
            return $root;
        }

        // Compile the root node into a single SQL fragment.
        $compiled = $this->compileNode($builder, $root, 0);

        if ($compiled instanceof WP_Error) {
            return $compiled;
        }

        // Push the single composed fragment into the builder.
        $builder->addPostCondition($compiled['sql'], $compiled['values']);

        return true;
    }

    // ── Normalization ─────────────────────────────────────────────────────

    /**
     * Normalize raw filter input into a canonical group node.
     *
     * - Single group node in a 1-element array → unwrap the group.
     * - Legacy flat list (items have no "type" key OR have type="condition") →
     *   wrap in an implicit top-level AND group.
     * - Otherwise the input IS the top-level group.
     *
     * @param list<array<string, mixed>> $filters
     * @return array<string, mixed>|WP_Error
     */
    private function normalizeInput(array $filters): array|WP_Error
    {
        // Top-level FilterGroup object passed directly (associative array, not list).
        // This happens when the REST body contains a bare group object instead of a
        // list-wrapped group, e.g. {"type":"group","combinator":"AND","children":[…]}.
        if ( isset( $filters['type'] ) && $filters['type'] === 'group' ) {
            return $filters;
        }

        // A single-element array whose only item is a group: unwrap it.
        if (count($filters) === 1 && isset($filters[0]['type']) && $filters[0]['type'] === 'group') {
            return $filters[0];
        }

        // Detect legacy flat list: every item either has no "type" key or has type="condition"
        // and carries a "field" key. If ANY item is a group, treat the whole input as a
        // (possibly mixed) list wrapped in an AND group.
        $allAreLegacyConditions = true;
        foreach ($filters as $item) {
            if (! is_array($item)) {
                $allAreLegacyConditions = false;
                break;
            }
            $type = $item['type'] ?? null;
            if ($type === 'group') {
                $allAreLegacyConditions = false;
                break;
            }
            // Legacy condition or explicit condition node — acceptable.
        }

        if ($allAreLegacyConditions) {
            // Wrap legacy flat conditions in an AND group.
            $children = [];
            foreach ($filters as $item) {
                $children[] = array_merge(['type' => 'condition'], $item);
            }
            return ['type' => 'group', 'combinator' => 'AND', 'children' => $children];
        }

        // Mixed input (groups + conditions): wrap everything in AND.
        return ['type' => 'group', 'combinator' => 'AND', 'children' => $filters];
    }

    // ── Node Compilation ──────────────────────────────────────────────────

    /**
     * Compile a single filter node (group or condition) into SQL.
     *
     * @param array<string, mixed> $node
     * @return array{sql: string, values: list<mixed>}|WP_Error
     */
    private function compileNode(QueryBuilder $builder, array $node, int $depth): array|WP_Error
    {
        // Determine node type. Nodes with a "field" key but no "type" are legacy conditions.
        $type = $node['type'] ?? (isset($node['field']) ? 'condition' : null);

        if ($type === null) {
            return new WP_Error(
                'wbm_invalid_filter_node',
                __('Filter node is missing a "type" key.', 'ihumbak-woo-bulk-edit'),
                ['status' => 400]
            );
        }

        if ($type === 'group') {
            return $this->compileGroup($builder, $node, $depth);
        }

        if ($type === 'condition') {
            return $this->compileCondition($builder, $node);
        }

        return new WP_Error(
            'wbm_invalid_filter_node',
            sprintf(__('Unknown filter node type "%s".', 'ihumbak-woo-bulk-edit'), $type),
            ['status' => 400]
        );
    }

    /**
     * Compile a group node.
     *
     * @param array<string, mixed> $node
     * @return array{sql: string, values: list<mixed>}|WP_Error
     */
    private function compileGroup(QueryBuilder $builder, array $node, int $depth): array|WP_Error
    {
        if ($depth > self::MAX_GROUP_DEPTH) {
            return new WP_Error(
                'wbm_group_depth_exceeded',
                sprintf(
                    __('Filter group nesting exceeds maximum depth of %d.', 'ihumbak-woo-bulk-edit'),
                    self::MAX_GROUP_DEPTH
                ),
                ['status' => 400]
            );
        }

        $combinator = strtoupper((string) ($node['combinator'] ?? ''));

        if (! in_array($combinator, ['AND', 'OR'], true)) {
            return new WP_Error(
                'wbm_invalid_combinator',
                sprintf(__('Invalid filter combinator "%s". Must be "AND" or "OR".', 'ihumbak-woo-bulk-edit'), $combinator),
                ['status' => 400]
            );
        }

        $children = $node['children'] ?? [];

        if (empty($children)) {
            // Empty group contributes nothing — return a tautology so the enclosing group is not broken.
            return ['sql' => '1=1', 'values' => []];
        }

        $parts  = [];
        $values = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                return new WP_Error(
                    'wbm_invalid_filter_node',
                    __('Filter group child must be an array.', 'ihumbak-woo-bulk-edit'),
                    ['status' => 400]
                );
            }

            $compiled = $this->compileNode($builder, $child, $depth + 1);

            if ($compiled instanceof WP_Error) {
                return $compiled;
            }

            $parts[]  = $compiled['sql'];
            $values   = array_merge($values, $compiled['values']);
        }

        $joined = implode(" {$combinator} ", $parts);
        $sql    = count($parts) > 1 ? "({$joined})" : $joined;

        return ['sql' => $sql, 'values' => $values];
    }

    /**
     * Compile a single condition node into SQL.
     *
     * @param array<string, mixed> $node
     * @return array{sql: string, values: list<mixed>}|WP_Error
     */
    private function compileCondition(QueryBuilder $builder, array $node): array|WP_Error
    {
        $fieldKey   = (string) ($node['field'] ?? '');
        $operatorId = strtoupper((string) ($node['operator'] ?? ''));
        $value      = $node['value'] ?? null;

        $field = $this->fieldRegistry->get($fieldKey);

        if ($field === null) {
            return new WP_Error(
                'wbm_invalid_filter_field',
                sprintf(__('Unknown filter field "%s".', 'ihumbak-woo-bulk-edit'), $fieldKey),
                ['status' => 400]
            );
        }

        if (! $field->isFilterable()) {
            return new WP_Error(
                'wbm_field_not_filterable',
                sprintf(__('Field "%s" is not filterable.', 'ihumbak-woo-bulk-edit'), $fieldKey),
                ['status' => 400]
            );
        }

        $operator = $this->operators->get($operatorId);

        if ($operator === null) {
            return new WP_Error(
                'wbm_invalid_operator',
                sprintf(__('Unknown operator "%s".', 'ihumbak-woo-bulk-edit'), $operatorId),
                ['status' => 400]
            );
        }

        return $this->buildConditionSql($builder, $fieldKey, $operator, $value);
    }

    // ── Condition SQL building ────────────────────────────────────────────

    /**
     * Build SQL for a single field + operator + value condition.
     *
     * Returns the SQL fragment and its bound values WITHOUT pushing them
     * into the builder (the caller handles composition).
     *
     * @return array{sql: string, values: list<mixed>}|WP_Error
     */
    private function buildConditionSql(
        QueryBuilder $builder,
        string $fieldKey,
        OperatorInterface $operator,
        mixed $value,
    ): array|WP_Error {
        // Post column fields (name, status, slug, description, …).
        $column = $builder->resolveColumn($fieldKey);

        if ($column !== null) {
            return $operator->toSql($column, $value);
        }

        // Meta fields (sku, regular_price, sale_price, stock_quantity, …).
        $metaKey = $builder->getMetaKey($fieldKey);

        if ($metaKey !== null) {
            $alias = $builder->joinMeta($metaKey);
            return $operator->toSql("{$alias}.meta_value", $value);
        }

        // Taxonomy fields (categories, tags, shipping_class).
        $taxonomy = $builder->getTaxonomyName($fieldKey);

        if ($taxonomy !== null) {
            return $this->buildTaxonomyConditionSql($builder, $taxonomy, $operator, $value);
        }

        // Field exists in the registry but resolves to no known column type.
        // Return a tautology so the query is not broken.
        return ['sql' => '1=1', 'values' => []];
    }

    /**
     * Build SQL for a taxonomy condition.
     *
     * @return array{sql: string, values: list<mixed>}
     */
    private function buildTaxonomyConditionSql(
        QueryBuilder $builder,
        string $taxonomy,
        OperatorInterface $operator,
        mixed $value,
    ): array {
        $operatorId = $operator->getIdentifier();

        // IS EMPTY / IS NOT EMPTY: existence check, no value needed.
        if ($operatorId === 'IS EMPTY') {
            return $builder->buildTaxonomyExistsSql($taxonomy, false);
        }

        if ($operatorId === 'IS NOT EMPTY') {
            return $builder->buildTaxonomyExistsSql($taxonomy, true);
        }

        // Negation semantics: "!=" / "NOT LIKE" on a taxonomy.
        $negate = in_array($operatorId, ['!=', 'NOT LIKE'], true);

        $positiveOperator = match ($operatorId) {
            '!='       => $this->operators->get('='),
            'NOT LIKE' => $this->operators->get('LIKE'),
            default    => $operator,
        };

        // Defensive: registered default operators always exist.
        if ($positiveOperator === null) {
            return ['sql' => '1=1', 'values' => []];
        }

        // term_id path (Issue #45): numeric non-zero value with = or !=.
        if (
            in_array($operatorId, ['=', '!='], true)
            && is_string($value)
            && $value !== ''
            && preg_match('/^[1-9]\d*$/', $value) === 1
        ) {
            $termId   = (int) $value;
            $expanded = [$termId];

            if (is_taxonomy_hierarchical($taxonomy)) {
                $children = get_term_children($termId, $taxonomy);
                if (is_array($children) && ! empty($children)) {
                    $expanded = array_merge($expanded, array_map('intval', $children));
                }
            }

            $expanded = array_values(
                array_unique(
                    array_filter($expanded, fn (int $id) => $id > 0)
                )
            );

            return $builder->buildTaxonomyTermIdSql($taxonomy, $expanded, $negate);
        }

        // Name-based path.
        $result = $positiveOperator->toSql('t.name', $value);
        return $builder->buildTaxonomySubquerySql($taxonomy, $result['sql'], $result['values'], $negate);
    }
}
