<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\NotEqualOperator;
use PHPUnit\Framework\TestCase;

final class NotEqualOperatorTest extends TestCase
{
    private NotEqualOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new NotEqualOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('!=', $this->operator->getIdentifier());
    }

    public function test_toSql_structure(): void
    {
        $result = $this->operator->toSql('col', 'val');

        self::assertSame('col != %s', $result['sql']);
        self::assertSame(['val'], $result['values']);
    }

    public function test_toSql_casts_value_to_string(): void
    {
        $result = $this->operator->toSql('col', 99);

        self::assertSame(['99'], $result['values']);
    }
}
