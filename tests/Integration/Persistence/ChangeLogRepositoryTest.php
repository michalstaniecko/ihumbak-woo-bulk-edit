<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Persistence;

use IhumbakWooBulkEdit\Persistence\ChangeLogRepository;
use IhumbakWooBulkEdit\Persistence\DatabaseMigrator;
use WP_UnitTestCase;

final class ChangeLogRepositoryTest extends WP_UnitTestCase
{
    private DatabaseMigrator $migrator;
    private ChangeLogRepository $repo;

    public function set_up(): void
    {
        parent::set_up();

        $this->migrator = new DatabaseMigrator();
        $this->migrator->migrate();
        $this->repo = new ChangeLogRepository($this->migrator);

        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $this->migrator->changeLogTable());
    }

    public function test_log_inserts_entry(): void
    {
        $this->repo->log(42, 'name', 'old name', 'new name', 7);

        $result = $this->repo->query();

        self::assertSame(1, $result['total']);
        self::assertCount(1, $result['items']);
        self::assertSame(42, $result['items'][0]['product_id']);
        self::assertSame('name', $result['items'][0]['field']);
        self::assertSame('old name', $result['items'][0]['old_value']);
        self::assertSame('new name', $result['items'][0]['new_value']);
        self::assertSame(7, $result['items'][0]['user_id']);
    }

    public function test_log_batch_inserts_multiple(): void
    {
        $this->repo->logBatch([
            ['product_id' => 1, 'field' => 'name', 'old_value' => 'a', 'new_value' => 'b'],
            ['product_id' => 2, 'field' => 'sku', 'old_value' => null, 'new_value' => 'X-1'],
            ['product_id' => 2, 'field' => 'regular_price', 'old_value' => '10.00', 'new_value' => '12.00'],
        ], 5);

        $result = $this->repo->query();

        self::assertSame(3, $result['total']);
    }

    public function test_log_batch_empty_is_noop(): void
    {
        $this->repo->logBatch([]);

        $result = $this->repo->query();

        self::assertSame(0, $result['total']);
    }

    public function test_query_filters_by_product(): void
    {
        $this->repo->logBatch([
            ['product_id' => 1, 'field' => 'name', 'old_value' => 'a', 'new_value' => 'b'],
            ['product_id' => 2, 'field' => 'name', 'old_value' => 'c', 'new_value' => 'd'],
            ['product_id' => 2, 'field' => 'sku', 'old_value' => 'x', 'new_value' => 'y'],
        ], 1);

        $result = $this->repo->query(['product_id' => 2]);

        self::assertSame(2, $result['total']);
        foreach ($result['items'] as $item) {
            self::assertSame(2, $item['product_id']);
        }
    }

    public function test_query_filters_by_field(): void
    {
        $this->repo->logBatch([
            ['product_id' => 1, 'field' => 'name', 'old_value' => 'a', 'new_value' => 'b'],
            ['product_id' => 1, 'field' => 'sku', 'old_value' => 'x', 'new_value' => 'y'],
        ], 1);

        $result = $this->repo->query(['field' => 'name']);

        self::assertSame(1, $result['total']);
        self::assertSame('name', $result['items'][0]['field']);
    }

    public function test_query_filters_by_user(): void
    {
        $this->repo->log(1, 'name', 'a', 'b', 5);
        $this->repo->log(1, 'sku', 'x', 'y', 9);

        $result = $this->repo->query(['user_id' => 5]);

        self::assertSame(1, $result['total']);
        self::assertSame(5, $result['items'][0]['user_id']);
    }

    public function test_query_paginates(): void
    {
        $entries = [];
        for ($i = 1; $i <= 25; $i++) {
            $entries[] = [
                'product_id' => $i,
                'field'      => 'name',
                'old_value'  => "old{$i}",
                'new_value'  => "new{$i}",
            ];
        }
        $this->repo->logBatch($entries, 1);

        $page1 = $this->repo->query(['page' => 1, 'per_page' => 10]);
        $page3 = $this->repo->query(['page' => 3, 'per_page' => 10]);

        self::assertSame(25, $page1['total']);
        self::assertSame(3, $page1['pages']);
        self::assertCount(10, $page1['items']);
        self::assertCount(5, $page3['items']);
    }

    public function test_query_orders_by_changed_at_desc(): void
    {
        $this->repo->log(1, 'name', 'old1', 'new1');
        // Ensure a different id ordering for the DESC tiebreak.
        $this->repo->log(2, 'name', 'old2', 'new2');

        $result = $this->repo->query();

        self::assertSame(2, $result['items'][0]['product_id']);
        self::assertSame(1, $result['items'][1]['product_id']);
    }

    public function test_encodes_array_values_as_json(): void
    {
        $this->repo->log(1, 'categories', [1, 2], [1, 2, 3]);

        $result = $this->repo->query();

        self::assertSame([1, 2], $result['items'][0]['old_value']);
        self::assertSame([1, 2, 3], $result['items'][0]['new_value']);
    }

    public function test_purge_older_than_deletes_old_rows(): void
    {
        global $wpdb;

        // Insert a fresh entry.
        $this->repo->log(1, 'name', 'a', 'b');

        // Insert an old entry directly.
        $wpdb->insert($this->migrator->changeLogTable(), [
            'user_id'    => 1,
            'product_id' => 99,
            'field'      => 'name',
            'old_value'  => '"old"',
            'new_value'  => '"new"',
            'changed_at' => gmdate('Y-m-d H:i:s', time() - (120 * DAY_IN_SECONDS)),
        ]);

        $deleted = $this->repo->purgeOlderThan(90);

        self::assertSame(1, $deleted);

        $remaining = $this->repo->query();
        self::assertSame(1, $remaining['total']);
        self::assertSame(1, $remaining['items'][0]['product_id']);
    }
}
