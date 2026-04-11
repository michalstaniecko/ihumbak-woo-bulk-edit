<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields\Core;

use IhumbakWooBulkEdit\Fields\Core\SkuField;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class SkuFieldTest extends TestCase
{
    private SkuField $field;

    protected function setUp(): void
    {
        $this->field = new SkuField();
    }

    public function test_key(): void
    {
        self::assertSame('sku', $this->field->getKey());
    }

    public function test_type(): void
    {
        self::assertSame(FieldType::Text, $this->field->getType());
    }

    public function test_sanitize_strips_tags(): void
    {
        self::assertSame('ABC-123', $this->field->sanitize('<script>ABC-123</script>'));
    }

    public function test_sanitize_trims(): void
    {
        self::assertSame('SKU-001', $this->field->sanitize('  SKU-001  '));
    }

    public function test_validate_always_passes(): void
    {
        self::assertTrue($this->field->validate(''));
        self::assertTrue($this->field->validate('ABC'));
    }
}
