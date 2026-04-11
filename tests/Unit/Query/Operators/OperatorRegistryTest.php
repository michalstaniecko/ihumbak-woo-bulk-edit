<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\OperatorInterface;
use IhumbakWooBulkEdit\Query\Operators\OperatorRegistry;
use PHPUnit\Framework\TestCase;

final class OperatorRegistryTest extends TestCase
{
    private OperatorRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new OperatorRegistry();
    }

    public function test_constructor_registers_six_operators(): void
    {
        self::assertNotNull($this->registry->get('='));
        self::assertNotNull($this->registry->get('!='));
        self::assertNotNull($this->registry->get('LIKE'));
        self::assertNotNull($this->registry->get('NOT LIKE'));
        self::assertNotNull($this->registry->get('IS EMPTY'));
        self::assertNotNull($this->registry->get('IS NOT EMPTY'));
    }

    public function test_get_is_case_insensitive(): void
    {
        $upper = $this->registry->get('LIKE');
        $lower = $this->registry->get('like');

        self::assertSame($upper, $lower);
    }

    public function test_get_unknown_returns_null(): void
    {
        self::assertNull($this->registry->get('>='));
    }

    public function test_has_returns_true_for_registered(): void
    {
        self::assertTrue($this->registry->has('='));
    }

    public function test_has_returns_false_for_unknown(): void
    {
        self::assertFalse($this->registry->has('>='));
    }

    public function test_has_is_case_insensitive(): void
    {
        self::assertTrue($this->registry->has('like'));
        self::assertTrue($this->registry->has('LIKE'));
    }

    public function test_register_custom_operator(): void
    {
        $custom = $this->createMock(OperatorInterface::class);
        $custom->method('getIdentifier')->willReturn('>=');

        $this->registry->register($custom);

        self::assertSame($custom, $this->registry->get('>='));
    }
}
