<?php
/**
 * Uninstall script for Ihumbak WooCommerce Bulk Edit.
 *
 * Removes all plugin data: custom tables, options, transients.
 *
 * @package IhumbakWooBulkEdit
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Drop custom tables.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wbm_saved_filters");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wbm_change_log");

// Clear scheduled cron events.
wp_clear_scheduled_hook('wbm_changelog_rotation');

// Delete options.
delete_option('wbm_db_version');
delete_option('wbm_changelog_retention_days');
delete_option('iwbe_settings');

// Delete transients.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbm_%' OR option_name LIKE '_transient_timeout_wbm_%'"
);
