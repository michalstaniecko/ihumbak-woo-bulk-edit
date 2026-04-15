<?php

/**
 * RegexpOperatorTest
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Query\Operators;

use IhumbakWooBulkEdit\Query\Operators\RegexpOperator;
use PHPUnit\Framework\TestCase;

final class RegexpOperatorTest extends TestCase
{
    private RegexpOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new RegexpOperator();
    }

    public function test_identifier(): void
    {
        self::assertSame('REGEXP', $this->operator->getIdentifier());
    }

    public function test_toSql_structure(): void
    {
        $result = $this->operator->toSql('col', '^foo');

        self::assertSame('col REGEXP %s', $result['sql']);
        self::assertSame(['^foo'], $result['values']);
    }

    public function test_toSql_casts_value_to_string(): void
    {
        $result = $this->operator->toSql('m0.meta_value', 42);

        self::assertSame('m0.meta_value REGEXP %s', $result['sql']);
        self::assertSame(['42'], $result['values']);
    }
}
