<?php

/**
 * Custom Taxonomy Field — dynamically-registered taxonomy field.
 *
 * Represents any WP taxonomy registered for the `product` post type that is
 * not already covered by a built-in core field. Instances are created and
 * registered by FieldRegistry::discoverCustomTaxonomies() at runtime.
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields\Core;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldType;

/**
 * Field representing a custom (non-built-in) product taxonomy.
 *
 * Custom taxonomy fields are filterable only — they cannot be edited or sorted
 * in the bulk-edit grid. The QueryBuilder and FilterParser handle them via the
 * standard TaxonomyMap lookup, just like CategoriesField and TagsField.
 */
final class CustomTaxonomyField extends AbstractField
{
    /**
     * @param string $fieldKey     The field key used in the filter/API (e.g. "product_brand").
     * @param string $fieldLabel   Human-readable label derived from the taxonomy's label.
     * @param string $taxonomySlug The WP taxonomy slug (e.g. "pwb-brand").
     */
    public function __construct(
        private readonly string $fieldKey,
        private readonly string $fieldLabel,
        private readonly string $taxonomySlug,
    ) {}

    public function getKey(): string
    {
        return $this->fieldKey;
    }

    public function getLabel(): string
    {
        return $this->fieldLabel;
    }

    public function getType(): FieldType
    {
        return FieldType::Taxonomy;
    }

    public function isEditable(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function isFilterable(): bool
    {
        return true;
    }

    /**
     * Sanitize an array of term IDs.
     *
     * @param mixed $value Raw value from the REST request.
     * @return list<int>
     */
    public function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map('absint', $value);
        }

        return [];
    }

    /**
     * Return the underlying WP taxonomy slug.
     *
     * Used by integrations and tests that need to map back to the WP taxonomy.
     */
    public function getTaxonomySlug(): string
    {
        return $this->taxonomySlug;
    }
}
