<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Persistence;

use WP_Error;

/**
 * CRUD for the {prefix}wbm_saved_filters table.
 *
 * Rows belong to a user (user_id > 0) or are shared team-wide (user_id = 0,
 * is_shared = 1). The definition column is a JSON blob that mirrors the
 * frontend SavedFilterDefinition shape: { filters[], search, sort }.
 *
 * @license GPL-2.0-or-later
 */
final class SavedFiltersRepository
{
    /**
     * Maximum number of saved filters per user (shared rows excluded).
     */
    public const MAX_PER_USER = 100;

    public function __construct(
        private readonly DatabaseMigrator $migrator,
    ) {}

    /**
     * Return all rows visible to $userId: own private rows + shared rows.
     *
     * @return list<array{id: int, user_id: int, name: string, definition: mixed, is_shared: bool, created_at: string, updated_at: string}>
     */
    public function listForUser(int $userId): array
    {
        global $wpdb;

        $table = $this->migrator->savedFiltersTable();

        $sql = "SELECT * FROM {$table}
                WHERE (user_id = %d OR (user_id = 0 AND is_shared = 1))
                ORDER BY updated_at DESC";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $userId), ARRAY_A);

        return array_map(fn (array $row): array => $this->hydrate($row), $rows ?: []);
    }

    /**
     * Find a single row by its primary key.
     *
     * @return array{id: int, user_id: int, name: string, definition: mixed, is_shared: bool, created_at: string, updated_at: string}|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $table = $this->migrator->savedFiltersTable();

        $sql = "SELECT * FROM {$table} WHERE id = %d LIMIT 1";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($wpdb->prepare($sql, $id), ARRAY_A);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Insert a new saved filter.
     *
     * @param int    $userId     Owner. Use 0 for shared rows (caller must ensure capability).
     * @param string $name       Display name (unique per user_id).
     * @param array  $definition Filter definition to JSON-encode.
     * @param bool   $isShared   Whether this is a shared (team) filter.
     *
     * @return int|WP_Error New row ID on success, or a WP_Error with code:
     *                       - wbm_filter_limit        — user already has MAX_PER_USER rows
     *                       - wbm_filter_name_conflict — name already taken for this user_id
     */
    public function create(int $userId, string $name, array $definition, bool $isShared): int|WP_Error
    {
        global $wpdb;

        // Enforce per-user limit (shared rows don't count toward any user).
        if ($userId > 0 && $this->countForUser($userId) >= self::MAX_PER_USER) {
            return new WP_Error(
                'wbm_filter_limit',
                sprintf(
                    /* translators: %d: maximum number of saved filters */
                    __('You cannot save more than %d filters.', 'ihumbak-woo-bulk-edit'),
                    self::MAX_PER_USER
                ),
                ['status' => 429]
            );
        }

        // Unique name per user_id (including user_id = 0 for shared).
        if ($this->uniqueNameExists($userId, $name)) {
            return new WP_Error(
                'wbm_filter_name_conflict',
                sprintf(
                    /* translators: %s: filter name */
                    __('A filter named "%s" already exists.', 'ihumbak-woo-bulk-edit'),
                    $name
                ),
                ['status' => 409]
            );
        }

        $now = current_time('mysql', true);

        $wpdb->insert(
            $this->migrator->savedFiltersTable(),
            [
                'user_id'    => $userId,
                'name'       => $name,
                'definition' => (string) wp_json_encode($definition),
                'is_shared'  => (int) $isShared,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing row. Pass null for any field you do not want to change.
     *
     * @return true|WP_Error
     */
    public function update(int $id, ?string $name, ?array $definition, ?bool $isShared): true|WP_Error
    {
        global $wpdb;

        $row = $this->find($id);
        if ($row === null) {
            return new WP_Error('wbm_filter_not_found', __('Filter not found.', 'ihumbak-woo-bulk-edit'), ['status' => 404]);
        }

        // Name uniqueness check (exclude the row being updated).
        if ($name !== null && $name !== $row['name']) {
            $userId = $row['user_id'];
            if ($this->uniqueNameExists($userId, $name, $id)) {
                return new WP_Error(
                    'wbm_filter_name_conflict',
                    sprintf(
                        /* translators: %s: filter name */
                        __('A filter named "%s" already exists.', 'ihumbak-woo-bulk-edit'),
                        $name
                    ),
                    ['status' => 409]
                );
            }
        }

        $data = ['updated_at' => current_time('mysql', true)];
        $formats = ['%s'];

        if ($name !== null) {
            $data['name'] = $name;
            $formats[] = '%s';
        }

        if ($definition !== null) {
            $data['definition'] = (string) wp_json_encode($definition);
            $formats[] = '%s';
        }

        if ($isShared !== null) {
            $data['is_shared'] = (int) $isShared;
            $formats[] = '%d';
        }

        $wpdb->update(
            $this->migrator->savedFiltersTable(),
            $data,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return true;
    }

    /**
     * Delete a row.
     *
     * @return bool True if a row was deleted.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $affected = (int) $wpdb->delete(
            $this->migrator->savedFiltersTable(),
            ['id' => $id],
            ['%d']
        );

        return $affected > 0;
    }

    /**
     * Count private (non-shared) rows belonging to $userId.
     */
    public function countForUser(int $userId): int
    {
        global $wpdb;

        $table = $this->migrator->savedFiltersTable();

        $sql = "SELECT COUNT(*) FROM {$table} WHERE user_id = %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, $userId));
    }

    /**
     * Hydrate a raw database row into a typed array.
     *
     * @param array<string, mixed> $row
     *
     * @return array{id: int, user_id: int, name: string, definition: mixed, is_shared: bool, created_at: string, updated_at: string}
     */
    private function hydrate(array $row): array
    {
        $raw = $row['definition'] ?? '{}';
        $definition = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $definition = [];
        }

        return [
            'id'         => (int) ($row['id'] ?? 0),
            'user_id'    => (int) ($row['user_id'] ?? 0),
            'name'       => (string) ($row['name'] ?? ''),
            'definition' => $definition,
            'is_shared'  => (bool) ($row['is_shared'] ?? false),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * Check whether a name is already taken for the given user_id.
     *
     * @param int      $userId    The owner's user_id (0 for shared).
     * @param string   $name      The candidate name.
     * @param int|null $excludeId Row ID to exclude (used during update).
     */
    private function uniqueNameExists(int $userId, string $name, ?int $excludeId = null): bool
    {
        global $wpdb;

        $table = $this->migrator->savedFiltersTable();

        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND name = %s AND id != %d";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count = (int) $wpdb->get_var($wpdb->prepare($sql, $userId, $name, $excludeId));
        } else {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND name = %s";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count = (int) $wpdb->get_var($wpdb->prepare($sql, $userId, $name));
        }

        return $count > 0;
    }
}
