<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields\Core;

use IhumbakWooBulkEdit\Fields\Core\RegularPriceField;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class RegularPriceFieldTest extends TestCase
{
    private RegularPriceField $field;

    protected function setUp(): void
    {
        $this->field = new RegularPriceField();
    }

    public function test_key(): void
    {
        self::assertSame('regular_price', $this->field->getKey());
    }

    public function test_type(): void
    {
        self::assertSame(FieldType::Price, $this->field->getType());
    }

    public function test_sanitize_empty_returns_empty_string(): void
    {
        self::assertSame('', $this->field->sanitize(''));
    }

    public function test_sanitize_null_returns_empty_string(): void
    {
        self::assertSame('', $this->field->sanitize(null));
    }

    public function test_sanitize_valid_number(): void
    {
        $result = $this->field->sanitize('19.99');
        self::assertIsString($result);
        self::assertSame('19.99', $result);
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
        self::assertTrue($this->field->validate('10.50'));
    }

    public function test_validate_zero_passes(): void
    {
        self::assertTrue($this->field->validate('0'));
    }

    public function test_validate_negative_fails(): void
    {
        $result = $this->field->validate('-5');
        self::assertIsString($result);
        self::assertStringContainsString('non-negative', $result);
    }

    public function test_validate_non_numeric_fails(): void
    {
        $result = $this->field->validate('abc');
        self::assertIsString($result);
        self::assertStringContainsString('non-negative', $result);
    }
}
