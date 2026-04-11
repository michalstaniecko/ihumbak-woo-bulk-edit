<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Security;

use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_REST_Request;
use WP_UnitTestCase;

final class CapabilityCheckerTest extends WP_UnitTestCase
{
    private CapabilityChecker $checker;

    public function set_up(): void
    {
        parent::set_up();
        $this->checker = new CapabilityChecker();
    }

    public function test_canRead_with_capable_user(): void
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        // Grant WooCommerce capabilities that don't exist in plain WP
        $user = wp_get_current_user();
        $user->add_cap('edit_products');
        $user->add_cap('delete_products');
        $user->add_cap('manage_woocommerce');

        self::assertTrue($this->checker->canRead());
    }

    public function test_canRead_with_subscriber_denied(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        self::assertFalse($this->checker->canRead());
    }

    public function test_canWrite_with_capable_user(): void
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        wp_get_current_user()->add_cap('edit_products');

        self::assertTrue($this->checker->canWrite());
    }

    public function test_canWrite_with_subscriber_denied(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        self::assertFalse($this->checker->canWrite());
    }

    public function test_canDelete_with_capable_user(): void
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        wp_get_current_user()->add_cap('delete_products');

        self::assertTrue($this->checker->canDelete());
    }

    public function test_canDelete_with_subscriber_denied(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        self::assertFalse($this->checker->canDelete());
    }

    public function test_canManageSharedFilters_with_capable_user(): void
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        wp_get_current_user()->add_cap('manage_woocommerce');

        self::assertTrue($this->checker->canManageSharedFilters());
    }

    public function test_canManageSharedFilters_with_subscriber_denied(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        self::assertFalse($this->checker->canManageSharedFilters());
    }

    public function test_permissionRead_returns_bool(): void
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        wp_get_current_user()->add_cap('edit_products');

        $request = new WP_REST_Request();
        $result = $this->checker->permissionRead($request);

        self::assertIsBool($result);
        self::assertTrue($result);
    }

    public function test_permissionWrite_returns_bool(): void
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        wp_get_current_user()->add_cap('edit_products');

        $request = new WP_REST_Request();
        self::assertIsBool($this->checker->permissionWrite($request));
    }

    public function test_permissionDelete_returns_bool(): void
    {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        wp_get_current_user()->add_cap('delete_products');

        $request = new WP_REST_Request();
        self::assertIsBool($this->checker->permissionDelete($request));
    }
}
