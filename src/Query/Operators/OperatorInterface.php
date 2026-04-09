<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Query\Operators;

/**
 * Contract for a filter operator that generates SQL conditions.
 */
interface OperatorInterface
{
    /**
     * Get the operator identifier (e.g. '=', '!=', 'LIKE').
     */
    public function getIdentifier(): string;

    /**
     * Generate SQL condition clause with placeholders.
     *
     * @return array{sql: string, values: list<mixed>} SQL with %s/%d placeholders and values for $wpdb->prepare().
     */
    public function toSql(string $column, mixed $value): array;
}
