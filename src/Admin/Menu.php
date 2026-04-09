<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Admin;

/**
 * Registers the admin submenu page under WooCommerce.
 */
final class Menu
{
    public const SLUG = 'ihumbak-woo-bulk-edit';
    public const CAPABILITY = 'edit_products';

    public function register(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Bulk Edit Products', 'ihumbak-woo-bulk-edit'),
            __('Bulk Edit', 'ihumbak-woo-bulk-edit'),
            self::CAPABILITY,
            self::SLUG,
            [new ScreenController(), 'render']
        );
    }
}
