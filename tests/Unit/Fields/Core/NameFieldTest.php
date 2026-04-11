<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields\Core;

use IhumbakWooBulkEdit\Fields\Core\NameField;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class NameFieldTest extends TestCase
{
    private NameField $field;

    protected function setUp(): void
    {
        $this->field = new NameField();
    }

    public function test_key(): void
    {
        self::assertSame('name', $this->field->getKey());
    }

    public function test_type(): void
    {
        self::assertSame(FieldType::Text, $this->field->getType());
    }

    public function test_is_editable(): void
    {
        self::assertTrue($this->field->isEditable());
    }

    public function test_is_sortable(): void
    {
        self::assertTrue($this->field->isSortable());
    }

    public function test_is_filterable(): void
    {
        self::assertTrue($this->field->isFilterable());
    }

    public function test_options_empty(): void
    {
        self::assertSame([], $this->field->getOptions());
    }

    public function test_sanitize_strips_tags(): void
    {
        self::assertSame('Foo', $this->field->sanitize('<b>Foo</b>'));
    }

    public function test_sanitize_trims_whitespace(): void
    {
        self::assertSame('Foo', $this->field->sanitize('  Foo  '));
    }

    public function test_validate_non_empty_passes(): void
    {
        self::assertTrue($this->field->validate('Product'));
    }

    public function test_validate_empty_string_fails(): void
    {
        $result = $this->field->validate('');
        self::assertIsString($result);
        self::assertStringContainsString('cannot be empty', $result);
    }

    public function test_validate_whitespace_only_fails(): void
    {
        $result = $this->field->validate('   ');
        self::assertIsString($result);
    }

    public function test_to_array_structure(): void
    {
        $array = $this->field->toArray();

        self::assertArrayHasKey('key', $array);
        self::assertArrayHasKey('label', $array);
        self::assertArrayHasKey('type', $array);
        self::assertArrayHasKey('editable', $array);
        self::assertArrayHasKey('sortable', $array);
        self::assertArrayHasKey('filterable', $array);
        self::assertArrayHasKey('options', $array);
        self::assertSame('name', $array['key']);
        self::assertSame('text', $array['type']);
    }
}
