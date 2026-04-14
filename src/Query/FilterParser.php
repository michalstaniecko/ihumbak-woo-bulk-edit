<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query;

use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Query\Operators\OperatorInterface;
use IhumbakWooBulkEdit\Query\Operators\OperatorRegistry;
use WP_Error;

/**
 * Parses filter JSON from the REST API and applies conditions to QueryBuilder.
 *
 * Expected filter format:
 * [
 *   { "field": "name", "operator": "LIKE", "value": "shirt" },
 *   { "field": "regular_price", "operator": "=", "value": "10" },
 *   { "field": "stock_quantity", "operator": "IS EMPTY" }
 * ]
 */
final class FilterParser
{
    private OperatorRegistry $operators;

    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
    ) {
        $this->operators = new OperatorRegistry();
    }

    /**
     * @param list<array{field: string, operator: string, value?: mixed}> $filters
     */
    public function apply(QueryBuilder $builder, array $filters): true|WP_Error
    {
        foreach ($filters as $index => $filter) {
            $fieldKey = $filter['field'] ?? '';
            $operatorId = strtoupper($filter['operator'] ?? '');
            $value = $filter['value'] ?? null;

            $field = $this->fieldRegistry->get($fieldKey);

            if ($field === null) {
                return new WP_Error(
                    'wbm_invalid_filter_field',
                    sprintf(__('Unknown filter field "%s" at index %d.', 'ihumbak-woo-bulk-edit'), $fieldKey, $index),
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
                    sprintf(__('Unknown operator "%s" at index %d.', 'ihumbak-woo-bulk-edit'), $operatorId, $index),
                    ['status' => 400]
                );
            }

            $this->applyCondition($builder, $fieldKey, $operator, $value);
        }

        return true;
    }

    private function applyCondition(
        QueryBuilder $builder,
        string $fieldKey,
        OperatorInterface $operator,
        mixed $value,
    ): void {
        // Post column fields (name, status, slug, description, ...).
        $column = $builder->resolveColumn($fieldKey);

        if ($column !== null) {
            $result = $operator->toSql($column, $value);
            $builder->addPostCondition($result['sql'], $result['values']);
            return;
        }

        // Meta fields (sku, regular_price, sale_price, stock_quantity, ...).
        $metaKey = $builder->getMetaKey($fieldKey);

        if ($metaKey !== null) {
            $alias = $builder->joinMeta($metaKey);
            $result = $operator->toSql("{$alias}.meta_value", $value);
            $builder->addPostCondition($result['sql'], $result['values']);
            return;
        }

        // Taxonomy fields (categories, tags, shipping_class).
        $taxonomy = $builder->getTaxonomyName($fieldKey);

        if ($taxonomy !== null) {
            $this->applyTaxonomyCondition($builder, $taxonomy, $operator, $value);
        }
    }

    private function applyTaxonomyCondition(
        QueryBuilder $builder,
        string $taxonomy,
        OperatorInterface $operator,
        mixed $value,
    ): void {
        $operatorId = $operator->getIdentifier();

        // IS EMPTY / IS NOT EMPTY: check existence of any term in the taxonomy,
        // independent of the (absent) value.
        if ($operatorId === 'IS EMPTY') {
            $builder->addTaxonomyExistsCondition($taxonomy, false);
            return;
        }

        if ($operatorId === 'IS NOT EMPTY') {
            $builder->addTaxonomyExistsCondition($taxonomy, true);
            return;
        }

        // Negation semantics: "!=" / "NOT LIKE" on a taxonomy mean
        // "product has no term matching the value" — implemented as NOT IN.
        // Note: products with zero terms in the taxonomy also match this, which
        // matches the intuitive "not in category X" reading.
        $negate = in_array($operatorId, ['!=', 'NOT LIKE'], true);

        $positiveOperator = match ($operatorId) {
            '!='       => $this->operators->get('='),
            'NOT LIKE' => $this->operators->get('LIKE'),
            default    => $operator,
        };

        // Defensive: registered default operators always exist; bail if somehow absent.
        if ($positiveOperator === null) {
            return;
        }

        // term_id path (Issue #45 + hierarchical fix): when operator is = or != AND
        // value is a non-empty numeric string, filter by term_id instead of t.name.
        // This is how the TaxonomyTermPicker passes IDs selected from the combobox.
        // We expand the selected term_id to include all descendant term IDs so that
        // products in child categories are also matched (hierarchical taxonomies like
        // product_cat).  For non-hierarchical taxonomies or leaf terms,
        // get_term_children() returns [] and the behaviour is identical to before.
        // LIKE / NOT LIKE always use the name path (backward compatibility).
        if (
            in_array($operatorId, ['=', '!='], true)
            && is_string($value)
            && $value !== ''
            && ctype_digit($value)
        ) {
            $termId   = (int) $value;
            $children = get_term_children($termId, $taxonomy);
            $expanded = [$termId];

            if (is_array($children) && ! empty($children)) {
                $expanded = array_merge($expanded, array_map('intval', $children));
            }

            // Deduplicate and drop any invalid IDs that may have crept in.
            $expanded = array_values(
                array_unique(
                    array_filter($expanded, fn (int $id) => $id > 0)
                )
            );

            $builder->addTaxonomyTermIdCondition($taxonomy, $expanded, $negate);
            return;
        }

        // Existing name-based path: LIKE, NOT LIKE, or = / != with a non-numeric value.
        $result = $positiveOperator->toSql('t.name', $value);
        $builder->addTaxonomyCondition($taxonomy, $result['sql'], $result['values'], $negate);
    }
}
