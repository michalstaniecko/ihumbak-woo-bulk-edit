<?php

/**
 * NotInOperator
 *
 * @package IhumbakWooBulkEdit
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

/**
 * Filter operator for SQL NOT IN (…) exclusion check.
 *
 * Accepts either an array of values or a comma-separated string.
 * Empty strings / whitespace-only entries are stripped.
 * An empty resulting list produces `1=0` (fail-closed).
 *
 * The IS NULL branch is required to handle LEFT JOINed meta rows where the
 * column may be NULL (row absent), meaning the product has no value for the
 * field and should still satisfy "not in [list]".
 */
final class NotInOperator implements OperatorInterface
{
    public function getIdentifier(): string
    {
        return 'NOT IN';
    }

    public function toSql(string $column, mixed $value): array
    {
        $items = $this->normalizeList($value);

        if (empty($items)) {
            return ['sql' => '1=0', 'values' => []];
        }

        $placeholders = implode(', ', array_fill(0, count($items), '%s'));

        return [
            'sql'    => "({$column} NOT IN ({$placeholders}) OR {$column} IS NULL)",
            'values' => $items,
        ];
    }

    /**
     * Normalize $value into a flat list of non-empty trimmed strings.
     *
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $items = explode(',', $value);
        } else {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }

        return $result;
    }
}
