<?php

/**
 * GreaterThanOrEqualOperator
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

/**
 * Filter operator for greater-than-or-equal numeric comparison.
 *
 * Uses `+ 0` cast so the comparison is numeric even for meta_value columns
 * that are stored as strings.
 */
final class GreaterThanOrEqualOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return '>=';
    }

    public function toSql(string $column, mixed $value): array
    {
        return [
            'sql'    => "{$column} + 0 >= %s",
            'values' => [(string) $value],
        ];
    }
}
