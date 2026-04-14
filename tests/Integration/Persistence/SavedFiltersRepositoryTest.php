<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Persistence;

use IhumbakWooBulkEdit\Persistence\DatabaseMigrator;
use IhumbakWooBulkEdit\Persistence\SavedFiltersRepository;
use WP_UnitTestCase;

final class SavedFiltersRepositoryTest extends WP_UnitTestCase
{
    private DatabaseMigrator $migrator;
    private SavedFiltersRepository $repo;

    public function set_up(): void
    {
        parent::set_up();

        $this->migrator = new DatabaseMigrator();
        $this->migrator->migrate();
        $this->repo = new SavedFiltersRepository($this->migrator);

        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $this->migrator->savedFiltersTable());
    }

    // ── Test 1 ─────────────────────────────────────────────────

    public function test_create_inserts_row_and_returns_id(): void
    {
        $definition = ['filters' => [], 'search' => '', 'sort' => ['field' => 'name', 'order' => 'asc']];
        $result = $this->repo->create(7, 'My Filter', $definition, false);

        self::assertIsInt($result);
        self::assertGreaterThan(0, $result);
    }

    // ── Test 2 ─────────────────────────────────────────────────

    public function test_find_returns_hydrated_row_with_decoded_json(): void
    {
        $definition = ['filters' => [['field' => 'name', 'operator' => '=', 'value' => 'foo']], 'search' => ''];
        $id = $this->repo->create(7, 'Find Me', $definition, false);

        self::assertIsInt($id);

        $row = $this->repo->find($id);

        self::assertNotNull($row);
        self::assertSame($id, $row['id']);
        self::assertSame(7, $row['user_id']);
        self::assertSame('Find Me', $row['name']);
        self::assertSame($definition, $row['definition']);
        self::assertFalse($row['is_shared']);
        self::assertArrayHasKey('created_at', $row);
        self::assertArrayHasKey('updated_at', $row);
    }

    // ── Test 3 ─────────────────────────────────────────────────

    public function test_find_returns_null_for_missing_id(): void
    {
        $row = $this->repo->find(999999);

        self::assertNull($row);
    }

    // ── Test 4 ─────────────────────────────────────────────────

    public function test_list_for_user_returns_only_own_and_shared(): void
    {
        // User 7 private filter
        $this->repo->create(7, 'User7 Private', ['filters' => []], false);

        // Shared filter (user_id = 0, is_shared = true)
        $this->repo->create(0, 'Shared Filter', ['filters' => []], true);

        // User 8 private filter — should NOT appear for user 7
        $this->repo->create(8, 'User8 Private', ['filters' => []], false);

        $list = $this->repo->listForUser(7);

        self::assertCount(2, $list);
        $names = array_column($list, 'name');
        self::assertContains('User7 Private', $names);
        self::assertContains('Shared Filter', $names);
        self::assertNotContains('User8 Private', $names);
    }

    // ── Test 5 ─────────────────────────────────────────────────

    public function test_create_rejects_duplicate_name_for_same_user(): void
    {
        $definition = ['filters' => []];
        $this->repo->create(7, 'Duplicate Name', $definition, false);

        $result = $this->repo->create(7, 'Duplicate Name', $definition, false);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('wbm_filter_name_conflict', $result->get_error_code());
    }

    // ── Test 6 ─────────────────────────────────────────────────

    public function test_create_allows_duplicate_name_across_users(): void
    {
        $definition = ['filters' => []];
        $id1 = $this->repo->create(7, 'Same Name', $definition, false);
        $id2 = $this->repo->create(8, 'Same Name', $definition, false);

        self::assertIsInt($id1);
        self::assertIsInt($id2);
        self::assertNotSame($id1, $id2);
    }

    // ── Test 7 ─────────────────────────────────────────────────

    public function test_create_hits_max_per_user_returns_error(): void
    {
        $definition = ['filters' => []];

        for ($i = 1; $i <= SavedFiltersRepository::MAX_PER_USER; $i++) {
            $result = $this->repo->create(77, "Filter {$i}", $definition, false);
            self::assertIsInt($result, "Expected int for filter #{$i}");
        }

        $result = $this->repo->create(77, 'One Too Many', $definition, false);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('wbm_filter_limit', $result->get_error_code());
    }

    // ── Test 8 ─────────────────────────────────────────────────

    public function test_update_changes_name_only(): void
    {
        $id = $this->repo->create(7, 'Old Name', ['filters' => []], false);
        self::assertIsInt($id);

        $result = $this->repo->update($id, 'New Name', null, null);

        self::assertTrue($result);

        $row = $this->repo->find($id);
        self::assertSame('New Name', $row['name']);
        // definition unchanged
        self::assertSame(['filters' => []], $row['definition']);
    }

    // ── Test 9 ─────────────────────────────────────────────────

    public function test_update_rejects_duplicate_name(): void
    {
        $this->repo->create(7, 'Filter A', ['filters' => []], false);
        $idB = $this->repo->create(7, 'Filter B', ['filters' => []], false);
        self::assertIsInt($idB);

        $result = $this->repo->update($idB, 'Filter A', null, null);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('wbm_filter_name_conflict', $result->get_error_code());
    }

    // ── Test 10 ────────────────────────────────────────────────

    public function test_delete_removes_row(): void
    {
        $id = $this->repo->create(7, 'To Delete', ['filters' => []], false);
        self::assertIsInt($id);

        $deleted = $this->repo->delete($id);

        self::assertTrue($deleted);
        self::assertNull($this->repo->find($id));
    }

    // ── Test 11 ────────────────────────────────────────────────

    public function test_count_for_user_excludes_shared(): void
    {
        $this->repo->create(7, 'User7 Filter 1', ['filters' => []], false);
        $this->repo->create(7, 'User7 Filter 2', ['filters' => []], false);
        // Shared filter should NOT count toward user 7's limit
        $this->repo->create(0, 'Shared Filter', ['filters' => []], true);

        $count = $this->repo->countForUser(7);

        self::assertSame(2, $count);
    }
}
