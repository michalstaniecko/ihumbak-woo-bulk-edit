<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration\Security;

use IhumbakWooBulkEdit\Security\RateLimiter;
use WP_Error;
use WP_UnitTestCase;

final class RateLimiterTest extends WP_UnitTestCase
{
    private RateLimiter $limiter;

    public function set_up(): void
    {
        parent::set_up();
        $this->limiter = new RateLimiter();
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    public function test_first_request_allowed(): void
    {
        self::assertTrue($this->limiter->check('test_action'));
    }

    public function test_ten_requests_allowed(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $result = $this->limiter->check('ten_test');
            self::assertTrue($result, "Request {$i} should be allowed");
        }
    }

    public function test_eleventh_request_blocked(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check('block_test');
        }

        $result = $this->limiter->check('block_test');

        self::assertInstanceOf(WP_Error::class, $result);
    }

    public function test_error_code_is_rate_limit_exceeded(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check('code_test');
        }

        $result = $this->limiter->check('code_test');

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('wbm_rate_limit_exceeded', $result->get_error_code());
    }

    public function test_error_status_is_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check('status_test');
        }

        $result = $this->limiter->check('status_test');

        self::assertInstanceOf(WP_Error::class, $result);
        $data = $result->get_error_data();
        self::assertSame(429, $data['status']);
    }

    public function test_different_actions_have_separate_limits(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check('actionA');
        }

        // actionA is exhausted, but actionB should still work
        $result = $this->limiter->check('actionB');

        self::assertTrue($result);
    }

    public function test_window_resets_after_expiry(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check('reset_test');
        }

        // Simulate window expiry by deleting the transient
        $userId = get_current_user_id();
        delete_transient("wbm_rate_reset_test_{$userId}");

        $result = $this->limiter->check('reset_test');

        self::assertTrue($result);
    }
}
