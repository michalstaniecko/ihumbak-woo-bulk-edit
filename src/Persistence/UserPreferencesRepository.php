<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Persistence;

/**
 * Stores per-user plugin preferences (column visibility) via WordPress user meta.
 *
 * Meta key: iwbe_column_visibility
 * Value shape: { hidden: string[], version: int }
 *
 * @license GPL-2.0-or-later
 */
final class UserPreferencesRepository
{
    public const META_KEY_COLUMN_VISIBILITY = 'iwbe_column_visibility';
    public const SCHEMA_VERSION = 1;

    /**
     * Columns that are always visible and cannot be hidden.
     */
    private const PINNED_COLUMNS = ['select', 'id'];

    /**
     * Returns the column visibility preferences for a user.
     *
     * @return array{hidden: list<string>, version: int}
     */
    public function getColumnVisibility(int $userId): array
    {
        $raw = get_user_meta($userId, self::META_KEY_COLUMN_VISIBILITY, true);

        if (! is_array($raw)) {
            return $this->defaultVisibility();
        }

        $hidden = $raw['hidden'] ?? [];

        if (! is_array($hidden)) {
            return $this->defaultVisibility();
        }

        // Sanitize each element and filter out pinned columns.
        $sanitized = array_values(
            array_filter(
                array_map('sanitize_key', $hidden),
                fn(string $key): bool => $key !== '' && ! in_array($key, self::PINNED_COLUMNS, true)
            )
        );

        return [
            'hidden'  => $sanitized,
            'version' => self::SCHEMA_VERSION,
        ];
    }

    /**
     * Persists column visibility preferences for a user.
     *
     * @param list<string> $hidden Column IDs to hide.
     */
    public function updateColumnVisibility(int $userId, array $hidden): void
    {
        // Sanitize and remove pinned columns.
        $sanitized = array_values(
            array_filter(
                array_map('sanitize_key', $hidden),
                fn(string $key): bool => $key !== '' && ! in_array($key, self::PINNED_COLUMNS, true)
            )
        );

        update_user_meta($userId, self::META_KEY_COLUMN_VISIBILITY, [
            'hidden'  => $sanitized,
            'version' => self::SCHEMA_VERSION,
        ]);
    }

    /**
     * Resets column visibility to the default state by removing the user meta.
     */
    public function resetColumnVisibility(int $userId): void
    {
        delete_user_meta($userId, self::META_KEY_COLUMN_VISIBILITY);
    }

    /**
     * @return array{hidden: list<string>, version: int}
     */
    private function defaultVisibility(): array
    {
        return [
            'hidden'  => [],
            'version' => self::SCHEMA_VERSION,
        ];
    }
}
