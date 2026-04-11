<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields\Core;

use IhumbakWooBulkEdit\Fields\Core\SalePriceField;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class SalePriceFieldTest extends TestCase
{
    private SalePriceField $field;

    protected function setUp(): void
    {
        $this->field = new SalePriceField();
    }

    public function test_key(): void
    {
        self::assertSame('sale_price', $this->field->getKey());
    }

    public function test_type(): void
    {
        self::assertSame(FieldType::Price, $this->field->getType());
    }

    public function test_sanitize_empty_returns_empty(): void
    {
        self::assertSame('', $this->field->sanitize(''));
    }

    public function test_sanitize_null_returns_empty(): void
    {
        self::assertSame('', $this->field->sanitize(null));
    }

    public function test_sanitize_valid_number(): void
    {
        self::assertSame('5.99', $this->field->sanitize('5.99'));
    }

    public function test_validate_empty_passes(): void
    {
        self::assertTrue($this->field->validate(''));
    }

    public function test_validate_null_passes(): void
    {
        self::assertTrue($this->field->validate(null));
    }

    public function test_validate_positive_passes(): void
    {
        self::assertTrue($this->field->validate('5.00'));
    }

    public function test_validate_negative_fails(): void
    {
        $result = $this->field->validate('-1');
        self::assertIsString($result);
        self::assertStringContainsString('non-negative', $result);
    }

    public function test_validate_non_numeric_fails(): void
    {
        $result = $this->field->validate('abc');
        self::assertIsString($result);
    }

    public function test_validate_lower_than_regular_passes(): void
    {
        self::assertTrue($this->field->validate('5', ['regular_price' => '10']));
    }

    public function test_validate_equal_to_regular_fails(): void
    {
        $result = $this->field->validate('10', ['regular_price' => '10']);
        self::assertIsString($result);
        self::assertStringContainsString('lower', $result);
    }

    public function test_validate_greater_than_regular_fails(): void
    {
        $result = $this->field->validate('15', ['regular_price' => '10']);
        self::assertIsString($result);
        self::assertStringContainsString('lower', $result);
    }

    public function test_validate_no_regular_price_context_passes(): void
    {
        self::assertTrue($this->field->validate('5', []));
    }

    public function test_validate_empty_regular_price_context_passes(): void
    {
        self::assertTrue($this->field->validate('5', ['regular_price' => '']));
    }

    public function test_validate_null_regular_price_context_passes(): void
    {
        self::assertTrue($this->field->validate('5', ['regular_price' => null]));
    }
}
