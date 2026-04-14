<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\UserPreferencesController;
use IhumbakWooBulkEdit\Persistence\UserPreferencesRepository;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for UserPreferencesController.
 *
 * @license GPL-2.0-or-later
 */
final class UserPreferencesControllerTest extends WP_UnitTestCase
{
    private UserPreferencesRepository $repo;

    public function set_up(): void
    {
        parent::set_up();

        $this->repo = new UserPreferencesRepository();

        add_action('rest_api_init', function (): void {
            $controller = new UserPreferencesController(
                $this->repo,
                new CapabilityChecker(),
            );
            $controller->register_routes();
        });

        do_action('rest_api_init', rest_get_server());
    }

    private function createEditorUser(): int
    {
        $userId = (int) self::factory()->user->create(['role' => 'editor']);
        $user = get_userdata($userId);
        $user->add_cap('edit_products');
        return $userId;
    }

    private function createSubscriberUser(): int
    {
        return (int) self::factory()->user->create(['role' => 'subscriber']);
    }

    // ── Test 1 ─────────────────────────────────────────────────

    public function test_get_returns_default_empty_hidden(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertArrayHasKey('hidden', $data);
        self::assertArrayHasKey('version', $data);
        self::assertSame([], $data['hidden']);
        self::assertSame(UserPreferencesRepository::SCHEMA_VERSION, $data['version']);
    }

    // ── Test 2 ─────────────────────────────────────────────────

    public function test_put_stores_hidden_columns(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        $putRequest = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $putRequest->set_header('Content-Type', 'application/json');
        $putRequest->set_body(wp_json_encode(['hidden' => ['description', 'weight']]));
        $putResponse = rest_get_server()->dispatch($putRequest);

        self::assertSame(200, $putResponse->get_status());

        $getRequest = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $getResponse = rest_get_server()->dispatch($getRequest);
        $data = $getResponse->get_data();

        self::assertContains('description', $data['hidden']);
        self::assertContains('weight', $data['hidden']);
    }

    // ── Test 3 ─────────────────────────────────────────────────

    public function test_put_filters_out_pinned_columns(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        $putRequest = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $putRequest->set_header('Content-Type', 'application/json');
        $putRequest->set_body(wp_json_encode(['hidden' => ['select', 'id', 'description']]));
        $putResponse = rest_get_server()->dispatch($putRequest);

        self::assertSame(200, $putResponse->get_status());

        $data = $putResponse->get_data();
        self::assertContains('description', $data['hidden']);
        self::assertNotContains('select', $data['hidden']);
        self::assertNotContains('id', $data['hidden']);
    }

    // ── Test 4 ─────────────────────────────────────────────────

    public function test_put_requires_edit_products_capability(): void
    {
        $userId = $this->createSubscriberUser();
        wp_set_current_user($userId);

        $putRequest = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $putRequest->set_header('Content-Type', 'application/json');
        $putRequest->set_body(wp_json_encode(['hidden' => ['description']]));
        $putResponse = rest_get_server()->dispatch($putRequest);

        self::assertGreaterThanOrEqual(400, $putResponse->get_status());
    }

    // ── Test 5 ─────────────────────────────────────────────────

    public function test_delete_resets_preferences(): void
    {
        $userId = $this->createEditorUser();
        wp_set_current_user($userId);

        // PUT some hidden columns
        $putRequest = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $putRequest->set_header('Content-Type', 'application/json');
        $putRequest->set_body(wp_json_encode(['hidden' => ['description', 'weight']]));
        rest_get_server()->dispatch($putRequest);

        // DELETE to reset
        $deleteRequest = new WP_REST_Request('DELETE', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $deleteResponse = rest_get_server()->dispatch($deleteRequest);

        self::assertSame(200, $deleteResponse->get_status());

        // GET should return empty hidden
        $getRequest = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $getResponse = rest_get_server()->dispatch($getRequest);
        $data = $getResponse->get_data();

        self::assertSame([], $data['hidden']);
    }

    // ── Test 6 ─────────────────────────────────────────────────

    public function test_put_persists_per_user(): void
    {
        $userId1 = $this->createEditorUser();
        $userId2 = $this->createEditorUser();

        // User 1 hides 'description'
        wp_set_current_user($userId1);
        $putRequest1 = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $putRequest1->set_header('Content-Type', 'application/json');
        $putRequest1->set_body(wp_json_encode(['hidden' => ['description']]));
        rest_get_server()->dispatch($putRequest1);

        // User 2 hides 'weight'
        wp_set_current_user($userId2);
        $putRequest2 = new WP_REST_Request('PUT', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $putRequest2->set_header('Content-Type', 'application/json');
        $putRequest2->set_body(wp_json_encode(['hidden' => ['weight']]));
        rest_get_server()->dispatch($putRequest2);

        // User 1's data should not include 'weight'
        wp_set_current_user($userId1);
        $getRequest1 = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $data1 = rest_get_server()->dispatch($getRequest1)->get_data();

        // User 2's data should not include 'description'
        wp_set_current_user($userId2);
        $getRequest2 = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/preferences/column-visibility');
        $data2 = rest_get_server()->dispatch($getRequest2)->get_data();

        self::assertContains('description', $data1['hidden']);
        self::assertNotContains('weight', $data1['hidden']);

        self::assertContains('weight', $data2['hidden']);
        self::assertNotContains('description', $data2['hidden']);
    }
}
