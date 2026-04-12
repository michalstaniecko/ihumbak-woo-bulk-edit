<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\ChangelogController;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Persistence\ChangeLogRepository;
use IhumbakWooBulkEdit\Persistence\DatabaseMigrator;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_REST_Request;
use WP_UnitTestCase;

final class ChangelogControllerTest extends WP_UnitTestCase
{
    private ChangeLogRepository $repo;

    public function set_up(): void
    {
        parent::set_up();

        $migrator = new DatabaseMigrator();
        $migrator->migrate();
        $this->repo = new ChangeLogRepository($migrator);

        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $migrator->changeLogTable());

        add_action('rest_api_init', function (): void {
            $controller = new ChangelogController(
                $this->repo,
                new FieldRegistry(),
                new CapabilityChecker(),
            );
            $controller->register_routes();
        });

        do_action('rest_api_init', rest_get_server());
    }

    private function createCapableUser(): int
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        get_userdata($user_id)->add_cap('edit_products');
        return $user_id;
    }

    public function test_get_changelog_requires_authentication(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/changelog');
        $response = rest_get_server()->dispatch($request);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    public function test_get_changelog_returns_200_when_authorized(): void
    {
        wp_set_current_user($this->createCapableUser());

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/changelog');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());
    }

    public function test_get_changelog_returns_expected_shape(): void
    {
        wp_set_current_user($this->createCapableUser());

        $this->repo->log(1, 'name', 'old', 'new');

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/changelog');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('total', $data);
        self::assertArrayHasKey('page', $data);
        self::assertArrayHasKey('per_page', $data);
        self::assertArrayHasKey('pages', $data);
        self::assertSame(1, $data['total']);
        self::assertCount(1, $data['items']);
    }

    public function test_get_changelog_filters_by_product_id(): void
    {
        wp_set_current_user($this->createCapableUser());

        $this->repo->logBatch([
            ['product_id' => 10, 'field' => 'name', 'old_value' => 'a', 'new_value' => 'b'],
            ['product_id' => 20, 'field' => 'name', 'old_value' => 'c', 'new_value' => 'd'],
        ], 1);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/changelog');
        $request->set_param('product_id', 10);
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertSame(1, $data['total']);
        self::assertSame(10, $data['items'][0]['product_id']);
    }

    public function test_get_changelog_rejects_unknown_field(): void
    {
        wp_set_current_user($this->createCapableUser());

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/changelog');
        $request->set_param('field', 'definitely_not_a_real_field');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(400, $response->get_status());
    }
}
