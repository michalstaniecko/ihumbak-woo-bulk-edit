<?php

/**
 * BetweenOperator
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

/**
 * Filter operator for SQL BETWEEN … AND … range check.
 *
 * Accepts:
 * - A 2-element array [min, max]
 * - A comma-separated string "min,max"
 *
 * Any other input (missing values, too few parts) produces `1=0` (fail-closed).
 * Uses `+ 0` cast for numeric comparisons on string-typed meta_value columns.
 */
final class BetweenOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return 'BETWEEN';
    }

    public function toSql(string $column, mixed $value): array
    {
        $pair = $this->normalizePair($value);

        if ($pair === null) {
            return ['sql' => '1=0', 'values' => []];
        }

        return [
            'sql'    => "{$column} + 0 BETWEEN %s AND %s",
            'values' => [$pair[0], $pair[1]],
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function normalizePair(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = array_values($value);
        } elseif (is_string($value) && $value !== '') {
            $items = explode(',', $value, 2);
        } else {
            return null;
        }

        if (count($items) < 2) {
            return null;
        }

        $min = trim((string) $items[0]);
        $max = trim((string) $items[1]);

        if ($min === '' || $max === '') {
            return null;
        }

        return [$min, $max];
    }
}
