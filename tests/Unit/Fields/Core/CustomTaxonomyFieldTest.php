<?php

/**
 * Unit tests for CustomTaxonomyField.
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields\Core;

use IhumbakWooBulkEdit\Fields\Core\CustomTaxonomyField;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IhumbakWooBulkEdit\Fields\Core\CustomTaxonomyField
 */
final class CustomTaxonomyFieldTest extends TestCase
{
    private CustomTaxonomyField $field;

    protected function setUp(): void
    {
        $this->field = new CustomTaxonomyField(
            'product_brand',
            'Brands',
            'product_brand'
        );
    }

    public function test_getKey_returns_constructor_key(): void
    {
        self::assertSame('product_brand', $this->field->getKey());
    }

    public function test_getLabel_returns_constructor_label(): void
    {
        self::assertSame('Brands', $this->field->getLabel());
    }

    public function test_getType_is_taxonomy(): void
    {
        self::assertSame(FieldType::Taxonomy, $this->field->getType());
    }

    public function test_isEditable_false_isSortable_false_isFilterable_true(): void
    {
        self::assertFalse($this->field->isEditable());
        self::assertFalse($this->field->isSortable());
        self::assertTrue($this->field->isFilterable());
    }

    public function test_sanitize_casts_array_to_positive_ints(): void
    {
        // absint() returns abs((int)$value), so '-3' → 3, '0' → 0.
        $result = $this->field->sanitize(['1', '5', '0', '-3', '10']);

        self::assertSame([1, 5, 0, 3, 10], $result);
    }

    public function test_sanitize_non_array_returns_empty_array(): void
    {
        self::assertSame([], $this->field->sanitize('not-an-array'));
        self::assertSame([], $this->field->sanitize(42));
        self::assertSame([], $this->field->sanitize(null));
    }

    public function test_toArray_shape_matches_taxonomy_field_contract(): void
    {
        $array = $this->field->toArray();

        self::assertSame('taxonomy', $array['type']);
        self::assertFalse($array['editable']);
        self::assertFalse($array['sortable']);
        self::assertTrue($array['filterable']);
        self::assertSame('product_brand', $array['key']);
        self::assertSame('Brands', $array['label']);
    }

    public function test_getTaxonomySlug_returns_constructor_slug(): void
    {
        self::assertSame('product_brand', $this->field->getTaxonomySlug());
    }

    public function test_different_key_and_slug(): void
    {
        $field = new CustomTaxonomyField('brands', 'Product Brands', 'pwb-brand');

        self::assertSame('brands', $field->getKey());
        self::assertSame('Product Brands', $field->getLabel());
        self::assertSame('pwb-brand', $field->getTaxonomySlug());
    }
}
