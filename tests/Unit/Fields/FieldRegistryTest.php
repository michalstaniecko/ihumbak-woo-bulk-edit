<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields;

use IhumbakWooBulkEdit\Fields\AbstractField;
use IhumbakWooBulkEdit\Fields\FieldInterface;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class FieldRegistryTest extends TestCase
{
    /**
     * Expected set of core field keys registered by FieldRegistry::registerCoreFields().
     *
     * Kept in the same order as registration in src/Fields/FieldRegistry.php so
     * tests stay in sync with the production registration order. Assertions that
     * don't care about order use assertEqualsCanonicalizing.
     */
    private const EXPECTED_CORE_KEYS = [
        // Identifiers & basic info.
        'name', 'slug', 'sku', 'status', 'catalog_visibility', 'featured', 'date_created',
        // Descriptions.
        'description', 'short_description',
        // Pricing.
        'regular_price', 'sale_price',
        // Stock & inventory.
        'manage_stock', 'stock_quantity', 'backorders', 'sold_individually',
        // Dimensions & shipping.
        'weight', 'length', 'width', 'height', 'shipping_class',
        // Downloadable & virtual.
        'virtual', 'downloadable', 'download_limit', 'download_expiry',
        // External product.
        'external_url', 'button_text',
        // Taxonomy.
        'categories', 'tags',
        // Images.
        'thumbnail', 'gallery',
        // Linked products.
        'cross_sells', 'upsells',
        // Meta.
        'purchase_note', 'reviews_allowed', 'menu_order',
    ];

    private FieldRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new FieldRegistry();
    }

    public function test_constructor_registers_expected_core_fields(): void
    {
        self::assertEqualsCanonicalizing(
            self::EXPECTED_CORE_KEYS,
            array_keys($this->registry->getAll())
        );
    }

    public function test_constructor_registers_expected_keys(): void
    {
        $keys = array_keys($this->registry->getAll());

        self::assertContains('name', $keys);
        self::assertContains('sku', $keys);
        self::assertContains('regular_price', $keys);
        self::assertContains('sale_price', $keys);
        self::assertContains('stock_quantity', $keys);
        self::assertContains('status', $keys);
    }

    public function test_get_existing_field(): void
    {
        $field = $this->registry->get('name');

        self::assertInstanceOf(FieldInterface::class, $field);
        self::assertSame('name', $field->getKey());
    }

    public function test_get_unknown_returns_null(): void
    {
        self::assertNull($this->registry->get('nonexistent'));
    }

    public function test_register_adds_custom_field(): void
    {
        $initialCount = count($this->registry->getAll());
        $mock = $this->createMockField('custom_field');

        $this->registry->register($mock);

        self::assertSame($mock, $this->registry->get('custom_field'));
        self::assertCount($initialCount + 1, $this->registry->getAll());
    }

    public function test_register_overwrites_existing_key(): void
    {
        $initialCount = count($this->registry->getAll());
        $replacement = $this->createMockField('name');

        $this->registry->register($replacement);

        self::assertSame($replacement, $this->registry->get('name'));
        self::assertCount($initialCount, $this->registry->getAll());
    }

    public function test_getEditable_excludes_non_editable(): void
    {
        $initialEditableCount = count($this->registry->getEditable());

        $nonEditable = $this->createMock(FieldInterface::class);
        $nonEditable->method('getKey')->willReturn('readonly_field');
        $nonEditable->method('isEditable')->willReturn(false);
        $nonEditable->method('isFilterable')->willReturn(true);
        $nonEditable->method('isSortable')->willReturn(true);

        $this->registry->register($nonEditable);

        $editable = $this->registry->getEditable();

        self::assertArrayNotHasKey('readonly_field', $editable);
        self::assertCount($initialEditableCount, $editable);
    }

    public function test_getFilterable_excludes_non_filterable(): void
    {
        $initialFilterableCount = count($this->registry->getFilterable());

        $nonFilterable = $this->createMock(FieldInterface::class);
        $nonFilterable->method('getKey')->willReturn('no_filter');
        $nonFilterable->method('isFilterable')->willReturn(false);

        $this->registry->register($nonFilterable);

        $filterable = $this->registry->getFilterable();

        self::assertArrayNotHasKey('no_filter', $filterable);
        self::assertCount($initialFilterableCount, $filterable);
    }

    public function test_getSortable_excludes_non_sortable(): void
    {
        $initialSortableCount = count($this->registry->getSortable());

        $nonSortable = $this->createMock(FieldInterface::class);
        $nonSortable->method('getKey')->willReturn('no_sort');
        $nonSortable->method('isSortable')->willReturn(false);

        $this->registry->register($nonSortable);

        $sortable = $this->registry->getSortable();

        self::assertArrayNotHasKey('no_sort', $sortable);
        self::assertCount($initialSortableCount, $sortable);
    }

    public function test_toArray_returns_list_of_arrays(): void
    {
        $result = $this->registry->toArray();

        self::assertCount(count($this->registry->getAll()), $result);

        foreach ($result as $item) {
            self::assertIsArray($item);
            self::assertArrayHasKey('key', $item);
            self::assertArrayHasKey('label', $item);
            self::assertArrayHasKey('type', $item);
        }
    }

    public function test_toArray_count_matches_getAll(): void
    {
        self::assertCount(
            count($this->registry->getAll()),
            $this->registry->toArray()
        );
    }

    private function createMockField(string $key): FieldInterface
    {
        $mock = $this->createMock(FieldInterface::class);
        $mock->method('getKey')->willReturn($key);
        $mock->method('isEditable')->willReturn(true);
        $mock->method('isFilterable')->willReturn(true);
        $mock->method('isSortable')->willReturn(true);
        $mock->method('toArray')->willReturn([
            'key' => $key,
            'label' => 'Mock',
            'type' => 'text',
            'editable' => true,
            'sortable' => true,
            'filterable' => true,
            'options' => [],
        ]);

        return $mock;
    }
}
