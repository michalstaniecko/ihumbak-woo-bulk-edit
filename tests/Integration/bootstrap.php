<?php

declare(strict_types=1);

// Disable update checker in tests to avoid PucFactory initialization issues.
if (! defined('IWBE_DISABLE_UPDATES')) {
    define('IWBE_DISABLE_UPDATES', true);
}

$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (! file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find {$_tests_dir}/includes/functions.php. Set WP_TESTS_DIR environment variable.\n";
    exit(1);
}

/*
 * Locate WooCommerce for integration tests.
 *
 * WooCommerce is NOT a Composer dev dependency (it's huge and has its own
 * build pipeline), so integration tests rely on a WC checkout that already
 * exists on disk. Resolution order:
 *
 *   1. `WC_PLUGIN_DIR` env var pointing to the directory containing
 *      `woocommerce.php` (e.g. `/path/to/woocommerce`).
 *   2. A sibling checkout next to this plugin workspace, as used on the
 *      primary dev machine:
 *      `~/Projects/woocommerce-plugins/ihumbak-woo-order-edit-history/woocommerce`
 *   3. The standard WP test plugin dir:
 *      `{WP_TESTS_DIR}/../wordpress/wp-content/plugins/woocommerce`
 *
 * If none of these exist, fail loud with instructions — we'd rather surface
 * the problem than run a half-crippled suite.
 */
$_wc_candidates = [];

$_wc_env = getenv('WC_PLUGIN_DIR');
if (is_string($_wc_env) && $_wc_env !== '') {
    $_wc_candidates[] = rtrim($_wc_env, '/');
}

$_wc_candidates[] = dirname(__DIR__, 5) . '/ihumbak-woo-order-edit-history/woocommerce';
$_wc_candidates[] = dirname($_tests_dir) . '/wordpress/wp-content/plugins/woocommerce';

$_wc_plugin_file = null;
foreach ($_wc_candidates as $_wc_dir) {
    if (is_string($_wc_dir) && file_exists($_wc_dir . '/woocommerce.php')) {
        $_wc_plugin_file = $_wc_dir . '/woocommerce.php';
        break;
    }
}

if ($_wc_plugin_file === null) {
    echo "Could not locate WooCommerce for integration tests.\n";
    echo "Set WC_PLUGIN_DIR to the directory containing woocommerce.php, or clone\n";
    echo "a WooCommerce checkout to one of:\n";
    foreach ($_wc_candidates as $_wc_dir) {
        echo "  - {$_wc_dir}\n";
    }
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

// WooCommerce must be loaded BEFORE our plugin so that WC_* classes and
// wc_* functions are available when our service constructors and field
// registrations run.
tests_add_filter('muplugins_loaded', static function () use ($_wc_plugin_file): void {
    // Needed by WC during plugin loading.
    if (! defined('WP_UNINSTALL_PLUGIN')) {
        $GLOBALS['wp_plugin_paths'] = $GLOBALS['wp_plugin_paths'] ?? [];
    }
    require_once $_wc_plugin_file;
    require dirname(__DIR__, 2) . '/ihumbak-woo-bulk-edit.php';
}, 1);

// WC needs its install routine to run (creates tables, roles, etc.) before
// tests that touch products execute. `wp-phpunit` fires `wp_install` once at
// bootstrap — we hook after that to install WC.
tests_add_filter('setup_theme', static function (): void {
    if (class_exists('WC_Install')) {
        \WC_Install::install();

        // Initialise roles so that `edit_products` / `delete_products` caps
        // exist on the administrator role, matching a real WC install.
        if (class_exists('WC_Post_types')) {
            \WC_Post_types::register_post_types();
            \WC_Post_types::register_taxonomies();
        }
    }
});

require $_tests_dir . '/includes/bootstrap.php';
