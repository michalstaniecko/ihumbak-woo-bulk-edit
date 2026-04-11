<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields;

use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class FieldTypeTest extends TestCase
{
    public function test_all_cases_count(): void
    {
        self::assertCount(12, FieldType::cases());
    }

    public function test_text_value(): void
    {
        self::assertSame('text', FieldType::Text->value);
    }

    public function test_price_value(): void
    {
        self::assertSame('price', FieldType::Price->value);
    }

    public function test_custom_meta_value(): void
    {
        self::assertSame('custom_meta', FieldType::CustomMeta->value);
    }

    public function test_from_valid_string(): void
    {
        self::assertSame(FieldType::Integer, FieldType::from('integer'));
    }

    public function test_tryFrom_invalid_returns_null(): void
    {
        self::assertNull(FieldType::tryFrom('bogus'));
    }
}
