<?php

/**
 * RegexpOperator
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

/**
 * Filter operator for MySQL REGEXP pattern matching.
 */
final class RegexpOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return 'REGEXP';
    }

    public function toSql(string $column, mixed $value): array
    {
        return [
            'sql'    => "{$column} REGEXP %s",
            'values' => [(string) $value],
        ];
    }
}
