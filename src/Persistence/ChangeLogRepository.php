<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Persistence;

/**
 * Reads and writes entries in the {prefix}wbm_change_log table.
 *
 * Values are stored as JSON-encoded strings so the original type (scalar,
 * array, null) can be round-tripped back to the UI.
 *
 * @license GPL-2.0-or-later
 */
final class ChangeLogRepository
{
    public function __construct(
        private readonly DatabaseMigrator $migrator,
    ) {}

    /**
     * Insert a single change log entry.
     */
    public function log(
        int $productId,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        ?int $userId = null,
    ): void {
        global $wpdb;

        $userId ??= get_current_user_id();

        $wpdb->insert(
            $this->migrator->changeLogTable(),
            [
                'user_id'    => (int) $userId,
                'product_id' => $productId,
                'field'      => $field,
                'old_value'  => $this->encodeValue($oldValue),
                'new_value'  => $this->encodeValue($newValue),
                'changed_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Insert a batch of entries in a single SQL statement.
     *
     * @param list<array{product_id: int, field: string, old_value: mixed, new_value: mixed}> $entries
     */
    public function logBatch(array $entries, ?int $userId = null): void
    {
        if ($entries === []) {
            return;
        }

        global $wpdb;

        $userId ??= get_current_user_id();
        $changedAt = current_time('mysql');
        $table = $this->migrator->changeLogTable();

        $placeholders = [];
        $values = [];

        foreach ($entries as $entry) {
            $placeholders[] = '(%d, %d, %s, %s, %s, %s)';
            $values[] = (int) $userId;
            $values[] = (int) $entry['product_id'];
            $values[] = (string) $entry['field'];
            $values[] = $this->encodeValue($entry['old_value']);
            $values[] = $this->encodeValue($entry['new_value']);
            $values[] = $changedAt;
        }

        $sql = "INSERT INTO {$table}
            (user_id, product_id, field, old_value, new_value, changed_at)
            VALUES " . implode(', ', $placeholders);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($wpdb->prepare($sql, $values));
    }

    /**
     * Query the change log with optional filters and pagination.
     *
     * @param array{
     *     product_id?: int,
     *     user_id?: int,
     *     field?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     page?: int,
     *     per_page?: int
     * } $args
     *
     * @return array{
     *     items: list<array{
     *         id: int,
     *         user_id: int,
     *         user_name: string,
     *         product_id: int,
     *         product_name: string,
     *         field: string,
     *         old_value: mixed,
     *         new_value: mixed,
     *         changed_at: string
     *     }>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     pages: int
     * }
     */
    public function query(array $args = []): array
    {
        global $wpdb;

        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = min(500, max(1, (int) ($args['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        [$where, $whereArgs] = $this->buildWhere($args);

        $table = $this->migrator->changeLogTable();

        $countSql = "SELECT COUNT(*) FROM {$table} {$where}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var(
            $whereArgs === [] ? $countSql : $wpdb->prepare($countSql, $whereArgs)
        );

        $selectSql = "SELECT id, user_id, product_id, field, old_value, new_value, changed_at
            FROM {$table}
            {$where}
            ORDER BY changed_at DESC, id DESC
            LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare($selectSql, [...$whereArgs, $perPage, $offset]),
            ARRAY_A
        );

        $items = array_map(fn (array $row): array => $this->hydrate($row), $rows ?: []);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /**
     * Delete all entries older than $days days. Used by the rotation cron.
     *
     * @return int Number of rows deleted.
     */
    public function purgeOlderThan(int $days): int
    {
        global $wpdb;

        $days = max(1, $days);
        $table = $this->migrator->changeLogTable();

        $sql = "DELETE FROM {$table} WHERE changed_at < (NOW() - INTERVAL %d DAY)";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->query($wpdb->prepare($sql, $days));
    }

    /**
     * @param array{
     *     product_id?: int,
     *     user_id?: int,
     *     field?: string,
     *     date_from?: string,
     *     date_to?: string
     * } $args
     *
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildWhere(array $args): array
    {
        $conditions = [];
        $values = [];

        if (! empty($args['product_id'])) {
            $conditions[] = 'product_id = %d';
            $values[] = (int) $args['product_id'];
        }

        if (! empty($args['user_id'])) {
            $conditions[] = 'user_id = %d';
            $values[] = (int) $args['user_id'];
        }

        if (! empty($args['field'])) {
            $conditions[] = 'field = %s';
            $values[] = (string) $args['field'];
        }

        if (! empty($args['date_from'])) {
            $conditions[] = 'changed_at >= %s';
            $values[] = (string) $args['date_from'];
        }

        if (! empty($args['date_to'])) {
            $conditions[] = 'changed_at <= %s';
            $values[] = (string) $args['date_to'];
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $values];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     id: int,
     *     user_id: int,
     *     user_name: string,
     *     product_id: int,
     *     product_name: string,
     *     field: string,
     *     old_value: mixed,
     *     new_value: mixed,
     *     changed_at: string
     * }
     */
    private function hydrate(array $row): array
    {
        $userId = (int) ($row['user_id'] ?? 0);
        $productId = (int) ($row['product_id'] ?? 0);

        $user = $userId > 0 ? get_userdata($userId) : null;
        $userName = $user !== false && $user !== null
            ? (string) $user->display_name
            : '';

        $productName = $productId > 0
            ? (string) get_the_title($productId)
            : '';

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'user_id'      => $userId,
            'user_name'    => $userName,
            'product_id'   => $productId,
            'product_name' => $productName,
            'field'        => (string) ($row['field'] ?? ''),
            'old_value'    => $this->decodeValue($row['old_value'] ?? null),
            'new_value'    => $this->decodeValue($row['new_value'] ?? null),
            'changed_at'   => (string) ($row['changed_at'] ?? ''),
        ];
    }

    private function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) wp_json_encode($value);
    }

    private function decodeValue(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }

        return $decoded;
    }
}
