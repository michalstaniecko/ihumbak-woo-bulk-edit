<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Persistence;

use IhumbakWooBulkEdit\Persistence\DatabaseMigrator;
use WP_UnitTestCase;

final class DatabaseMigratorTest extends WP_UnitTestCase
{
    private function tableExists(string $table): bool
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
            $table
        ));

        return (int) $count === 1;
    }

    public function test_migrate_creates_change_log_table(): void
    {
        global $wpdb;

        $migrator = new DatabaseMigrator();
        $migrator->migrate();

        // Probe the table with a direct SELECT — if it doesn't exist, this returns an error.
        $wpdb->suppress_errors(true);
        $result = $wpdb->query('SELECT 1 FROM ' . $migrator->changeLogTable() . ' LIMIT 0');
        $wpdb->suppress_errors(false);

        self::assertNotFalse($result, 'Change log table should exist after migrate(). wpdb error: ' . $wpdb->last_error);
    }

    public function test_migrate_creates_saved_filters_table(): void
    {
        global $wpdb;

        $migrator = new DatabaseMigrator();
        $migrator->migrate();

        $wpdb->suppress_errors(true);
        $result = $wpdb->query('SELECT 1 FROM ' . $migrator->savedFiltersTable() . ' LIMIT 0');
        $wpdb->suppress_errors(false);

        self::assertNotFalse($result, 'Saved filters table should exist after migrate(). wpdb error: ' . $wpdb->last_error);
    }

    public function test_migrate_updates_version_option(): void
    {
        delete_option(DatabaseMigrator::VERSION_OPTION);

        $migrator = new DatabaseMigrator();
        $migrator->migrate();

        self::assertSame(
            DatabaseMigrator::SCHEMA_VERSION,
            get_option(DatabaseMigrator::VERSION_OPTION)
        );
    }

    public function test_maybe_migrate_is_noop_when_version_current(): void
    {
        update_option(DatabaseMigrator::VERSION_OPTION, DatabaseMigrator::SCHEMA_VERSION);

        $migrator = new DatabaseMigrator();
        // Should not throw or require dbDelta.
        $migrator->maybeMigrate();

        self::assertSame(
            DatabaseMigrator::SCHEMA_VERSION,
            get_option(DatabaseMigrator::VERSION_OPTION)
        );
    }

    public function test_change_log_table_has_expected_columns(): void
    {
        global $wpdb;

        $migrator = new DatabaseMigrator();
        $migrator->migrate();

        $columns = $wpdb->get_col('DESCRIBE ' . $migrator->changeLogTable(), 0);

        self::assertContains('id', $columns);
        self::assertContains('user_id', $columns);
        self::assertContains('product_id', $columns);
        self::assertContains('field', $columns);
        self::assertContains('old_value', $columns);
        self::assertContains('new_value', $columns);
        self::assertContains('changed_at', $columns);
    }
}
