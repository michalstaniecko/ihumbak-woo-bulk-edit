<?php
/**
 * Plugin Name:       Ihumbak WooCommerce Bulk Edit
 * Plugin URI:        https://github.com/michalstaniecko/ihumbak-woo-bulk-edit
 * Description:       Bulk edit WooCommerce products with preview & commit UX, SQL-like filtering, formula engine, and audit log with rollback.
 * Version:           0.1.5
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Michal Staniecko
 * Author URI:        https://github.com/michalstaniecko
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ihumbak-woo-bulk-edit
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.8
 *
 * @package IhumbakWooBulkEdit
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('IWBE_VERSION', '0.1.5');
define('IWBE_PLUGIN_FILE', __FILE__);
define('IWBE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IWBE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IWBE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader.
 */
if (! file_exists(IWBE_PLUGIN_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'Ihumbak WooCommerce Bulk Edit: dependencies not installed. Run <code>composer install</code> in the plugin directory.',
                'ihumbak-woo-bulk-edit'
            )
        );
    });
    return;
}

require_once IWBE_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Declare HPOS compatibility.
 */
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Check requirements and boot the plugin.
 */
add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__(
                    'Ihumbak WooCommerce Bulk Edit requires WooCommerce to be installed and active.',
                    'ihumbak-woo-bulk-edit'
                )
            );
        });
        return;
    }

    \IhumbakWooBulkEdit\Plugin::instance()->boot();
});

/**
 * Activation hook.
 */
register_activation_hook(__FILE__, static function (): void {
    if (! class_exists('WooCommerce')) {
        wp_die(
            esc_html__(
                'Ihumbak WooCommerce Bulk Edit requires WooCommerce to be installed and active.',
                'ihumbak-woo-bulk-edit'
            ),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    \IhumbakWooBulkEdit\Plugin::instance()->activate();
});

/**
 * Deactivation hook.
 */
register_deactivation_hook(__FILE__, static function (): void {
    \IhumbakWooBulkEdit\Plugin::instance()->deactivate();
});
