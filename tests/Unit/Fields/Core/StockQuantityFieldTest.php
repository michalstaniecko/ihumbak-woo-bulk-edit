<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields\Core;

use IhumbakWooBulkEdit\Fields\Core\StockQuantityField;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class StockQuantityFieldTest extends TestCase
{
    private StockQuantityField $field;

    protected function setUp(): void
    {
        $this->field = new StockQuantityField();
    }

    public function test_key(): void
    {
        self::assertSame('stock_quantity', $this->field->getKey());
    }

    public function test_type(): void
    {
        self::assertSame(FieldType::Integer, $this->field->getType());
    }

    public function test_sanitize_empty_returns_null(): void
    {
        self::assertNull($this->field->sanitize(''));
    }

    public function test_sanitize_null_returns_null(): void
    {
        self::assertNull($this->field->sanitize(null));
    }

    public function test_sanitize_numeric_string_returns_int(): void
    {
        self::assertSame(42, $this->field->sanitize('42'));
    }

    public function test_sanitize_float_truncates(): void
    {
        self::assertSame(3, $this->field->sanitize('3.7'));
    }

    public function test_validate_empty_passes(): void
    {
        self::assertTrue($this->field->validate(''));
    }

    public function test_validate_null_passes(): void
    {
        self::assertTrue($this->field->validate(null));
    }

    public function test_validate_numeric_passes(): void
    {
        self::assertTrue($this->field->validate('10'));
    }

    public function test_validate_negative_numeric_passes(): void
    {
        self::assertTrue($this->field->validate('-5'));
    }

    public function test_validate_non_numeric_fails(): void
    {
        $result = $this->field->validate('abc');
        self::assertIsString($result);
        self::assertStringContainsString('must be a number', $result);
    }
}
