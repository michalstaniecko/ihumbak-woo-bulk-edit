<?php

/**
 * FilterDefinitionValidator — stateless validator for saved-filter definitions.
 *
 * Validates the `definition` array submitted to the Filters REST API before
 * persisting it. Both the legacy flat-list format and the new FilterGroup tree
 * format are accepted.
 *
 * Rules:
 *   - `filters` key is required; any other keys (search, sort) are optional.
 *   - Legacy flat list: each item must have `field` and `operator` string keys.
 *   - Group tree: `type === 'group'`, `combinator ∈ {AND, OR}`, `children` is
 *     an array of conditions or nested groups. Max depth 5, max total nodes 200.
 *   - An entirely empty `filters` value (empty array OR group with no children)
 *     yields `wbm_filter_empty`.
 *   - Depth > 5 yields `wbm_filter_too_deep`.
 *   - Node count > 200 yields `wbm_filter_invalid_definition`.
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query;

use WP_Error;

final class FilterDefinitionValidator
{
    /** Maximum allowed nesting depth for group nodes (root = depth 0). */
    private const MAX_DEPTH = 5;

    /** Maximum total number of nodes (conditions + groups) allowed in the tree. */
    private const MAX_NODES = 200;

    /**
     * Validate a raw `definition` array.
     *
     * @param array<string, mixed> $definition
     * @return null|WP_Error  null = valid, WP_Error = invalid
     */
    public function validate(array $definition): ?WP_Error
    {
        if (! array_key_exists('filters', $definition)) {
            return new WP_Error(
                'wbm_filter_invalid_definition',
                __('Filter definition must contain a "filters" key.', 'ihumbak-woo-bulk-edit'),
                ['status' => 400]
            );
        }

        $filters = $definition['filters'];

        // ── Group tree format ──────────────────────────────────────────────────
        if (is_array($filters) && isset($filters['type']) && $filters['type'] === 'group') {
            return $this->validateGroup($filters, 0, $this->countNodes($filters));
        }

        // ── Legacy flat list ───────────────────────────────────────────────────
        if (is_array($filters) && array_is_list($filters)) {
            if (count($filters) === 0) {
                return new WP_Error(
                    'wbm_filter_empty',
                    __('Filter definition contains no conditions.', 'ihumbak-woo-bulk-edit'),
                    ['status' => 400]
                );
            }

            // Compute total node count across the entire flat list so that groups
            // embedded in a flat list are checked against the global 200-node limit,
            // not just their own local subtree size.
            $totalFlatNodes = 0;
            foreach ($filters as $item) {
                $totalFlatNodes += is_array($item) ? $this->countNodes($item) : 1;
            }

            foreach ($filters as $index => $item) {
                if (! is_array($item)) {
                    return new WP_Error(
                        'wbm_filter_invalid_definition',
                        sprintf(
                            __('Filter condition at index %d must be an object.', 'ihumbak-woo-bulk-edit'),
                            $index
                        ),
                        ['status' => 400]
                    );
                }

                $itemType = $item['type'] ?? null;

                // If it's explicitly a group inside a flat list that's fine —
                // route it through group validation using the total flat-list node count.
                if ($itemType === 'group') {
                    $err = $this->validateGroup($item, 0, $totalFlatNodes);
                    if ($err !== null) {
                        return $err;
                    }
                    continue;
                }

                // Legacy condition or explicit condition node.
                if (! isset($item['field']) || ! is_string($item['field']) || $item['field'] === '') {
                    return new WP_Error(
                        'wbm_filter_invalid_definition',
                        sprintf(
                            __('Filter condition at index %d is missing a "field" key.', 'ihumbak-woo-bulk-edit'),
                            $index
                        ),
                        ['status' => 400]
                    );
                }

                if (! isset($item['operator']) || ! is_string($item['operator']) || $item['operator'] === '') {
                    return new WP_Error(
                        'wbm_filter_invalid_definition',
                        sprintf(
                            __('Filter condition at index %d is missing an "operator" key.', 'ihumbak-woo-bulk-edit'),
                            $index
                        ),
                        ['status' => 400]
                    );
                }
            }

            return null;
        }

        return new WP_Error(
            'wbm_filter_invalid_definition',
            __('The "filters" value must be an array or a group object.', 'ihumbak-woo-bulk-edit'),
            ['status' => 400]
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Recursively validate a group node.
     *
     * @param array<string, mixed> $group
     */
    private function validateGroup(array $group, int $depth, int $totalNodes): ?WP_Error
    {
        if ($depth > self::MAX_DEPTH) {
            return new WP_Error(
                'wbm_filter_too_deep',
                sprintf(
                    __('Filter group nesting exceeds maximum depth of %d.', 'ihumbak-woo-bulk-edit'),
                    self::MAX_DEPTH
                ),
                ['status' => 400]
            );
        }

        if ($totalNodes > self::MAX_NODES) {
            return new WP_Error(
                'wbm_filter_invalid_definition',
                sprintf(
                    __('Filter definition exceeds maximum node count of %d.', 'ihumbak-woo-bulk-edit'),
                    self::MAX_NODES
                ),
                ['status' => 400]
            );
        }

        $combinator = strtoupper((string) ($group['combinator'] ?? ''));
        if (! in_array($combinator, ['AND', 'OR'], true)) {
            return new WP_Error(
                'wbm_filter_invalid_definition',
                sprintf(
                    __('Invalid combinator "%s". Must be "AND" or "OR".', 'ihumbak-woo-bulk-edit'),
                    $group['combinator'] ?? ''
                ),
                ['status' => 400]
            );
        }

        $children = $group['children'] ?? [];

        if (! is_array($children) || count($children) === 0) {
            return new WP_Error(
                'wbm_filter_empty',
                __('Filter definition contains no conditions.', 'ihumbak-woo-bulk-edit'),
                ['status' => 400]
            );
        }

        foreach ($children as $child) {
            if (! is_array($child)) {
                return new WP_Error(
                    'wbm_filter_invalid_definition',
                    __('Each child in a filter group must be an object.', 'ihumbak-woo-bulk-edit'),
                    ['status' => 400]
                );
            }

            $childType = $child['type'] ?? null;

            if ($childType === 'group') {
                // Empty nested groups are no-ops (the frontend strips them before
                // saving, but skip them gracefully here for robustness).
                $nestedChildren = $child['children'] ?? [];
                if (is_array($nestedChildren) && count($nestedChildren) === 0) {
                    continue;
                }
                $err = $this->validateGroup($child, $depth + 1, $totalNodes);
                if ($err !== null) {
                    return $err;
                }
                continue;
            }

            if ($childType === 'condition' || isset($child['field'])) {
                // Validate as a condition node.
                if (! isset($child['field']) || ! is_string($child['field']) || $child['field'] === '') {
                    return new WP_Error(
                        'wbm_filter_invalid_definition',
                        __('A filter condition is missing a "field" key.', 'ihumbak-woo-bulk-edit'),
                        ['status' => 400]
                    );
                }
                if (! isset($child['operator']) || ! is_string($child['operator']) || $child['operator'] === '') {
                    return new WP_Error(
                        'wbm_filter_invalid_definition',
                        __('A filter condition is missing an "operator" key.', 'ihumbak-woo-bulk-edit'),
                        ['status' => 400]
                    );
                }
                continue;
            }

            // Unknown node — missing both 'type' and 'field'.
            return new WP_Error(
                'wbm_filter_invalid_definition',
                __('A filter group child is missing a "type" or "field" key.', 'ihumbak-woo-bulk-edit'),
                ['status' => 400]
            );
        }

        return null;
    }

    /**
     * Count the total number of nodes (groups + conditions) in the tree.
     *
     * @param array<string, mixed> $node
     */
    private function countNodes(array $node): int
    {
        $count = 1; // This node itself.

        $children = $node['children'] ?? [];
        if (! is_array($children)) {
            return $count;
        }

        foreach ($children as $child) {
            if (is_array($child)) {
                $count += $this->countNodes($child);
            }
        }

        return $count;
    }
}
