<?php

/**
 * LessThanOrEqualOperatorTest
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\LessThanOrEqualOperator;
use PHPUnit\Framework\TestCase;

final class LessThanOrEqualOperatorTest extends TestCase
{
    private LessThanOrEqualOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new LessThanOrEqualOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('<=', $this->operator->getIdentifier());
    }

    public function test_toSql_structure(): void
    {
        $result = $this->operator->toSql('col', '10');

        self::assertSame('col + 0 <= %s', $result['sql']);
        self::assertSame(['10'], $result['values']);
    }

    public function test_toSql_casts_value_to_string(): void
    {
        $result = $this->operator->toSql('m0.meta_value', 42);

        self::assertSame('m0.meta_value + 0 <= %s', $result['sql']);
        self::assertSame(['42'], $result['values']);
    }
}
