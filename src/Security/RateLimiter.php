<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Security;

use WP_Error;

/**
 * Rate limiter for batch endpoints using WordPress transients.
 */
final class RateLimiter
{
    private const MAX_REQUESTS = 10;
    private const WINDOW_SECONDS = 60;

    /**
     * Check if the current user has exceeded the rate limit.
     *
     * @return true|WP_Error True if allowed, WP_Error if rate limited.
     */
    public function check(string $action = 'batch'): true|WP_Error
    {
        $userId = get_current_user_id();
        $key = sprintf('wbm_rate_%s_%d', $action, $userId);
        $data = get_transient($key);

        if ($data === false) {
            set_transient($key, ['count' => 1, 'start' => time()], self::WINDOW_SECONDS);
            return true;
        }

        $count = (int) ($data['count'] ?? 0);
        $start = (int) ($data['start'] ?? 0);

        if ((time() - $start) >= self::WINDOW_SECONDS) {
            set_transient($key, ['count' => 1, 'start' => time()], self::WINDOW_SECONDS);
            return true;
        }

        if ($count >= self::MAX_REQUESTS) {
            return new WP_Error(
                'wbm_rate_limit_exceeded',
                __('Rate limit exceeded. Please wait before sending more requests.', 'ihumbak-woo-bulk-edit'),
                ['status' => 429]
            );
        }

        set_transient($key, ['count' => $count + 1, 'start' => $start], self::WINDOW_SECONDS);

        return true;
    }
}
