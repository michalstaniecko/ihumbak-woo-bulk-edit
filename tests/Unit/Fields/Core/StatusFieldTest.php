<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Fields\Core;

use IhumbakWooBulkEdit\Fields\Core\StatusField;
use IhumbakWooBulkEdit\Fields\FieldType;
use PHPUnit\Framework\TestCase;

final class StatusFieldTest extends TestCase
{
    private StatusField $field;

    protected function setUp(): void
    {
        $this->field = new StatusField();
    }

    public function test_key(): void
    {
        self::assertSame('status', $this->field->getKey());
    }

    public function test_type(): void
    {
        self::assertSame(FieldType::Select, $this->field->getType());
    }

    public function test_options_contains_five_statuses(): void
    {
        $options = $this->field->getOptions();

        self::assertCount(5, $options);
        self::assertArrayHasKey('publish', $options);
        self::assertArrayHasKey('draft', $options);
        self::assertArrayHasKey('pending', $options);
        self::assertArrayHasKey('private', $options);
        self::assertArrayHasKey('trash', $options);
    }

    public function test_sanitize_valid_status_passes_through(): void
    {
        self::assertSame('publish', $this->field->sanitize('publish'));
    }

    public function test_sanitize_invalid_falls_back_to_draft(): void
    {
        self::assertSame('draft', $this->field->sanitize('bogus'));
    }

    public function test_sanitize_empty_falls_back_to_draft(): void
    {
        self::assertSame('draft', $this->field->sanitize(''));
    }

    /**
     * @dataProvider validStatusProvider
     */
    public function test_validate_valid_status(string $status): void
    {
        self::assertTrue($this->field->validate($status));
    }

    public static function validStatusProvider(): array
    {
        return [
            'publish' => ['publish'],
            'draft'   => ['draft'],
            'pending' => ['pending'],
            'private' => ['private'],
            'trash'   => ['trash'],
        ];
    }

    public function test_validate_invalid_status_fails(): void
    {
        $result = $this->field->validate('bogus');
        self::assertIsString($result);
        self::assertStringContainsString('Invalid status', $result);
        self::assertStringContainsString('bogus', $result);
    }

    public function test_to_array_includes_options(): void
    {
        $array = $this->field->toArray();

        self::assertSame('status', $array['key']);
        self::assertSame('select', $array['type']);
        self::assertCount(5, $array['options']);
    }
}
