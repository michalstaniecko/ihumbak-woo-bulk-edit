<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Admin;

/**
 * Enqueues JS and CSS assets only on the plugin admin page.
 */
final class AssetsLoader
{
    public function enqueue(string $hookSuffix): void
    {
        if (! $this->isPluginScreen($hookSuffix)) {
            return;
        }

        $assetFile = IWBE_PLUGIN_DIR . 'assets/build/app.asset.php';

        if (! file_exists($assetFile)) {
            return;
        }

        $asset = require $assetFile;

        wp_enqueue_script(
            'ihumbak-woo-bulk-edit-app',
            IWBE_PLUGIN_URL . 'assets/build/app.js',
            $asset['dependencies'] ?? [],
            $asset['version'] ?? IWBE_VERSION,
            true
        );

        wp_enqueue_style(
            'ihumbak-woo-bulk-edit-app',
            IWBE_PLUGIN_URL . 'assets/build/app.css',
            [],
            $asset['version'] ?? IWBE_VERSION
        );

        wp_localize_script('ihumbak-woo-bulk-edit-app', 'iwbeData', [
            'restUrl'                  => esc_url_raw(rest_url('ihumbak-woo-bulk-edit/v1/')),
            'nonce'                    => wp_create_nonce('wp_rest'),
            'adminUrl'                 => esc_url_raw(admin_url()),
            'canManageSharedFilters'   => current_user_can('manage_woocommerce'),
        ]);
    }

    private function isPluginScreen(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, Menu::SLUG);
    }
}
