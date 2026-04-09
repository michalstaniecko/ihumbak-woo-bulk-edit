<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Security;

use WP_REST_Request;

/**
 * Centralized capability checks for REST endpoint permission callbacks.
 */
final class CapabilityChecker
{
    public function canRead(): bool
    {
        return current_user_can('edit_products');
    }

    public function canWrite(): bool
    {
        return current_user_can('edit_products');
    }

    public function canDelete(): bool
    {
        return current_user_can('delete_products');
    }

    public function canManageSharedFilters(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    /**
     * Permission callback for read endpoints.
     */
    public function permissionRead(WP_REST_Request $request): bool
    {
        return $this->canRead();
    }

    /**
     * Permission callback for write endpoints.
     */
    public function permissionWrite(WP_REST_Request $request): bool
    {
        return $this->canWrite();
    }

    /**
     * Permission callback for delete endpoints.
     */
    public function permissionDelete(WP_REST_Request $request): bool
    {
        return $this->canDelete();
    }
}
