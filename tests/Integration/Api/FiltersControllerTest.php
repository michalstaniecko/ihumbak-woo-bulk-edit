<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\FiltersController;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Persistence\DatabaseMigrator;
use IhumbakWooBulkEdit\Persistence\SavedFiltersRepository;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_REST_Request;
use WP_UnitTestCase;

final class FiltersControllerTest extends WP_UnitTestCase
{
    private SavedFiltersRepository $repo;
    private DatabaseMigrator $migrator;

    public function set_up(): void
    {
        parent::set_up();

        $this->migrator = new DatabaseMigrator();
        $this->migrator->migrate();
        $this->repo = new SavedFiltersRepository($this->migrator);

        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $this->migrator->savedFiltersTable());

        add_action('rest_api_init', function (): void {
            $controller = new FiltersController(
                $this->repo,
                new CapabilityChecker(),
                new FieldRegistry(),
            );
            $controller->register_routes();
        });

        do_action('rest_api_init', rest_get_server());
    }

    private function createEditorUser(): int
    {
        $userId = self::factory()->user->create(['role' => 'editor']);
        $user = get_userdata($userId);
        $user->add_cap('edit_products');
        return $userId;
    }

    private function createAdminUser(): int
    {
        $userId = self::factory()->user->create(['role' => 'administrator']);
        $user = get_userdata($userId);
        $user->add_cap('edit_products');
        $user->add_cap('manage_woocommerce');
        return $userId;
    }

    private function validDefinition(): array
    {
        return ['filters' => [], 'search' => '', 'sort' => ['field' => 'name', 'order' => 'asc']];
    }

    // ── Test 1 ─────────────────────────────────────────────────

    public function test_get_filters_requires_auth(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/filters');
        $response = rest_get_server()->dispatch($request);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    // ── Test 2 ─────────────────────────────────────────────────

    public function test_get_filters_returns_empty_list(): void
    {
        wp_set_current_user($this->createEditorUser());

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/filters');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertArrayHasKey('items', $data);
        self::assertCount(0, $data['items']);
    }

    // ── Test 3 ─────────────────────────────────────────────────

    public function test_get_filters_includes_shared_filters(): void
    {
        // Create shared filter as admin
        $adminId = $this->createAdminUser();
        wp_set_current_user($adminId);

        $postRequest = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $postRequest->set_header('Content-Type', 'application/json');
        $postRequest->set_body(wp_json_encode([
            'name'       => 'Shared Filter',
            'definition' => $this->validDefinition(),
            'is_shared'  => true,
        ]));
        $postResponse = rest_get_server()->dispatch($postRequest);
        self::assertSame(201, $postResponse->get_status());

        // Now switch to editor — they should see the shared filter
        wp_set_current_user($this->createEditorUser());

        $getRequest = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/filters');
        $getResponse = rest_get_server()->dispatch($getRequest);
        $data = $getResponse->get_data();

        self::assertSame(200, $getResponse->get_status());
        $names = array_column($data['items'], 'name');
        self::assertContains('Shared Filter', $names);
    }

    // ── Test 4 ─────────────────────────────────────────────────

    public function test_post_filter_creates_private_filter(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode([
            'name'       => 'My Filter',
            'definition' => $this->validDefinition(),
            'is_shared'  => false,
        ]));
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertSame(201, $response->get_status());
        self::assertArrayHasKey('id', $data);
        self::assertGreaterThan(0, $data['id']);
        self::assertSame('My Filter', $data['name']);
        self::assertFalse($data['is_shared']);
        self::assertSame($userId, $data['user_id']);
    }

    // ── Test 5 ─────────────────────────────────────────────────

    public function test_post_filter_rejects_empty_name(): void
    {
        wp_set_current_user($this->createEditorUser());

        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode([
            'name'       => '',
            'definition' => $this->validDefinition(),
            'is_shared'  => false,
        ]));
        $response = rest_get_server()->dispatch($request);

        self::assertSame(400, $response->get_status());
    }

    // ── Test 6 ─────────────────────────────────────────────────

    public function test_post_filter_editor_cannot_share(): void
    {
        // Editor does NOT have manage_woocommerce
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode([
            'name'       => 'Editor Shared',
            'definition' => $this->validDefinition(),
            'is_shared'  => true,
        ]));
        $response = rest_get_server()->dispatch($request);

        self::assertSame(403, $response->get_status());
        $data = $response->get_data();
        self::assertSame('wbm_cannot_share_filters', $data['code']);
    }

    // ── Test 7 ─────────────────────────────────────────────────

    public function test_post_filter_rejects_duplicate_name(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        $body = wp_json_encode([
            'name'       => 'Dup',
            'definition' => $this->validDefinition(),
            'is_shared'  => false,
        ]);

        $r1 = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $r1->set_header('Content-Type', 'application/json');
        $r1->set_body($body);
        rest_get_server()->dispatch($r1);

        $r2 = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $r2->set_header('Content-Type', 'application/json');
        $r2->set_body($body);
        $response = rest_get_server()->dispatch($r2);

        self::assertSame(409, $response->get_status());
        $data = $response->get_data();
        self::assertSame('wbm_filter_name_conflict', $data['code']);
    }

    // ── Test 8 ─────────────────────────────────────────────────

    public function test_post_filter_rejects_at_max_limit(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        // Insert max filters directly via repo to skip the HTTP overhead
        $definition = $this->validDefinition();
        for ($i = 1; $i <= SavedFiltersRepository::MAX_PER_USER; $i++) {
            $result = $this->repo->create($userId, "Filter {$i}", $definition, false);
            self::assertIsInt($result, "Expected int for filter #{$i}");
        }

        $request = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode([
            'name'       => 'Over Limit',
            'definition' => $definition,
            'is_shared'  => false,
        ]));
        $response = rest_get_server()->dispatch($request);

        self::assertSame(429, $response->get_status());
        $data = $response->get_data();
        self::assertSame('wbm_filter_limit', $data['code']);
    }

    // ── Test 9 ─────────────────────────────────────────────────

    public function test_put_filter_updates_name(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        // Create
        $postRequest = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $postRequest->set_header('Content-Type', 'application/json');
        $postRequest->set_body(wp_json_encode([
            'name'       => 'Old Name',
            'definition' => $this->validDefinition(),
            'is_shared'  => false,
        ]));
        $postResponse = rest_get_server()->dispatch($postRequest);
        self::assertSame(201, $postResponse->get_status());
        $id = $postResponse->get_data()['id'];

        // Update
        $putRequest = new WP_REST_Request('PUT', "/ihumbak-woo-bulk-edit/v1/filters/{$id}");
        $putRequest->set_header('Content-Type', 'application/json');
        $putRequest->set_body(wp_json_encode(['name' => 'New Name']));
        $putResponse = rest_get_server()->dispatch($putRequest);
        $data = $putResponse->get_data();

        self::assertSame(200, $putResponse->get_status());
        self::assertSame('New Name', $data['name']);
    }

    // ── Test 10 ────────────────────────────────────────────────

    public function test_put_filter_cannot_modify_other_users_filter(): void
    {
        $ownerUserId = $this->createEditorUser();
        $attackerUserId = $this->createEditorUser();

        // Create as owner
        wp_set_current_user($ownerUserId);
        $postRequest = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $postRequest->set_header('Content-Type', 'application/json');
        $postRequest->set_body(wp_json_encode([
            'name'       => 'Owner Filter',
            'definition' => $this->validDefinition(),
            'is_shared'  => false,
        ]));
        $postResponse = rest_get_server()->dispatch($postRequest);
        self::assertSame(201, $postResponse->get_status());
        $id = $postResponse->get_data()['id'];

        // Try to modify as different user
        wp_set_current_user($attackerUserId);
        $putRequest = new WP_REST_Request('PUT', "/ihumbak-woo-bulk-edit/v1/filters/{$id}");
        $putRequest->set_header('Content-Type', 'application/json');
        $putRequest->set_body(wp_json_encode(['name' => 'Hacked']));
        $putResponse = rest_get_server()->dispatch($putRequest);

        self::assertSame(403, $putResponse->get_status());
    }

    // ── Test 11 ────────────────────────────────────────────────

    public function test_delete_filter_removes_row(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        // Create
        $postRequest = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $postRequest->set_header('Content-Type', 'application/json');
        $postRequest->set_body(wp_json_encode([
            'name'       => 'To Delete',
            'definition' => $this->validDefinition(),
            'is_shared'  => false,
        ]));
        $postResponse = rest_get_server()->dispatch($postRequest);
        self::assertSame(201, $postResponse->get_status());
        $id = $postResponse->get_data()['id'];

        // Delete
        $deleteRequest = new WP_REST_Request('DELETE', "/ihumbak-woo-bulk-edit/v1/filters/{$id}");
        $deleteResponse = rest_get_server()->dispatch($deleteRequest);
        $data = $deleteResponse->get_data();

        self::assertSame(200, $deleteResponse->get_status());
        self::assertTrue($data['deleted']);
        self::assertSame($id, $data['id']);

        // Verify gone
        $getRequest = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/filters');
        $getResponse = rest_get_server()->dispatch($getRequest);
        self::assertCount(0, $getResponse->get_data()['items']);
    }

    // ── Test 12 ────────────────────────────────────────────────

    public function test_delete_filter_editor_cannot_delete_shared(): void
    {
        // Admin creates shared filter
        $adminId = $this->createAdminUser();
        wp_set_current_user($adminId);

        $postRequest = new WP_REST_Request('POST', '/ihumbak-woo-bulk-edit/v1/filters');
        $postRequest->set_header('Content-Type', 'application/json');
        $postRequest->set_body(wp_json_encode([
            'name'       => 'Shared',
            'definition' => $this->validDefinition(),
            'is_shared'  => true,
        ]));
        $postResponse = rest_get_server()->dispatch($postRequest);
        self::assertSame(201, $postResponse->get_status());
        $id = $postResponse->get_data()['id'];

        // Editor tries to delete the shared filter
        $editorId = $this->createEditorUser();
        wp_set_current_user($editorId);

        $deleteRequest = new WP_REST_Request('DELETE', "/ihumbak-woo-bulk-edit/v1/filters/{$id}");
        $deleteResponse = rest_get_server()->dispatch($deleteRequest);

        self::assertSame(403, $deleteResponse->get_status());
    }
}
