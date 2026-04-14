<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Api;

use IhumbakWooBulkEdit\Api\FieldsController;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_REST_Request;
use WP_UnitTestCase;

final class FieldsControllerTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        // Register routes inside rest_api_init to avoid "incorrect usage" notice
        add_action('rest_api_init', static function (): void {
            $controller = new FieldsController(
                new FieldRegistry(),
                new CapabilityChecker(),
            );
            $controller->register_routes();
        });

        // Force REST server to re-initialize
        do_action('rest_api_init', rest_get_server());
    }

    private function createCapableUser(): int
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        get_userdata($user_id)->add_cap('edit_products');
        return $user_id;
    }

    public function test_get_fields_returns_200(): void
    {
        wp_set_current_user($this->createCapableUser());

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/fields');
        $response = rest_get_server()->dispatch($request);

        self::assertSame(200, $response->get_status());
    }

    public function test_get_fields_returns_all_registered_fields(): void
    {
        wp_set_current_user($this->createCapableUser());

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/fields');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertIsArray($data);

        $registry = new FieldRegistry();
        self::assertCount(count($registry->getAll()), $data);
        self::assertSame(
            array_keys($registry->getAll()),
            array_column($data, 'key')
        );
    }

    public function test_get_fields_unauthenticated_returns_error(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/fields');
        $response = rest_get_server()->dispatch($request);

        self::assertGreaterThanOrEqual(400, $response->get_status());
    }

    public function test_get_fields_field_structure(): void
    {
        wp_set_current_user($this->createCapableUser());

        $request = new WP_REST_Request('GET', '/ihumbak-woo-bulk-edit/v1/fields');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        self::assertIsArray($data);

        foreach ($data as $field) {
            self::assertArrayHasKey('key', $field);
            self::assertArrayHasKey('label', $field);
            self::assertArrayHasKey('type', $field);
            self::assertArrayHasKey('editable', $field);
            self::assertArrayHasKey('sortable', $field);
            self::assertArrayHasKey('filterable', $field);
            self::assertArrayHasKey('options', $field);
        }
    }
}
