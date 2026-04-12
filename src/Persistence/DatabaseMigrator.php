<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Persistence;

/**
 * Creates and updates plugin custom tables via dbDelta().
 *
 * Schema version is tracked in the `wbm_db_version` option. On every bootstrap
 * the plugin calls {@see maybeMigrate()}: if the stored version differs from
 * {@see SCHEMA_VERSION}, the migrator runs dbDelta() and updates the option.
 *
 * Tables:
 * - {prefix}wbm_saved_filters — user-saved filter presets (JSON blob).
 * - {prefix}wbm_change_log    — audit log of product field changes.
 *
 * @license GPL-2.0-or-later
 */
final class DatabaseMigrator
{
    /**
     * Current schema version. Bump whenever a table definition changes.
     */
    public const SCHEMA_VERSION = '1.0.0';

    /**
     * Option key storing the installed schema version.
     */
    public const VERSION_OPTION = 'wbm_db_version';

    /**
     * Run migrations if the installed version differs from the target.
     */
    public function maybeMigrate(): void
    {
        $installed = (string) get_option(self::VERSION_OPTION, '');

        if ($installed === self::SCHEMA_VERSION) {
            return;
        }

        $this->migrate();
    }

    /**
     * Run migrations unconditionally. Intended for activation hooks.
     */
    public function migrate(): void
    {
        global $wpdb;

        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $savedFiltersTable = $this->savedFiltersTable();
        $changeLogTable = $this->changeLogTable();

        // Saved filters — user-owned filter presets stored as JSON.
        $savedFiltersSql = "CREATE TABLE {$savedFiltersTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(191) NOT NULL,
            definition LONGTEXT NOT NULL,
            is_shared TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_shared (is_shared)
        ) {$charsetCollate};";

        // Change log — audit trail of product field changes.
        $changeLogSql = "CREATE TABLE {$changeLogTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            field VARCHAR(100) NOT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            changed_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY field (field),
            KEY changed_at (changed_at)
        ) {$charsetCollate};";

        dbDelta($savedFiltersSql);
        dbDelta($changeLogSql);

        update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    public function savedFiltersTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wbm_saved_filters';
    }

    public function changeLogTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wbm_change_log';
    }
}
