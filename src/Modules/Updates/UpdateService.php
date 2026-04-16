<?php
/**
 * Update Service.
 *
 * Handles automatic plugin updates from GitHub releases.
 *
 * @package IhumbakWooBulkEdit\Modules\Updates
 */

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Modules\Updates;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Service for handling automatic plugin updates from GitHub.
 */
class UpdateService
{
    /**
     * Default GitHub repository URL.
     */
    public const DEFAULT_REPOSITORY_URL = 'https://github.com/michalstaniecko/ihumbak-woo-bulk-edit/';

    /**
     * Plugin slug.
     */
    public const PLUGIN_SLUG = 'ihumbak-woo-bulk-edit';

    /**
     * Update checker instance.
     *
     * @var \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker|\YahnisElsts\PluginUpdateChecker\v5p6\Plugin\UpdateChecker|\YahnisElsts\PluginUpdateChecker\v5p6\Theme\UpdateChecker|null
     */
    private $update_checker = null;

    /**
     * Check if updates are enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool
    {
        if (defined('IWBE_DISABLE_UPDATES') && IWBE_DISABLE_UPDATES) {
            return false;
        }

        /**
         * Filter whether automatic updates are enabled.
         *
         * @param bool $enabled Whether updates are enabled. Default true.
         */
        return (bool) apply_filters('ihumbak_woo_bulk_edit_updates_enabled', true);
    }

    /**
     * Initialize the update checker.
     *
     * @return void
     */
    public function init(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        $repository_url = $this->get_repository_url();
        $plugin_file    = $this->get_plugin_file();

        $this->update_checker = PucFactory::buildUpdateChecker(
            $repository_url,
            $plugin_file,
            self::PLUGIN_SLUG
        );

        // Enable release assets for ZIP downloads from GitHub releases.
        $api = $this->update_checker->getVcsApi();
        if (method_exists($api, 'enableReleaseAssets')) {
            $api->enableReleaseAssets();
        }

        // Configure authentication if token is available.
        $token = $this->get_github_access_token();
        if (! empty($token)) {
            $this->update_checker->setAuthentication($token);
        }

        // Add filter for update info modification.
        $this->update_checker->addFilter(
            'request_info_result',
            [$this, 'filter_update_info']
        );
    }

    /**
     * Force check for updates.
     *
     * @return object|null Update information or null if no update available.
     */
    public function check_for_updates(): ?object
    {
        if (! $this->update_checker) {
            return null;
        }

        return $this->update_checker->checkForUpdates();
    }

    /**
     * Get the repository URL.
     *
     * @return string
     */
    public function get_repository_url(): string
    {
        /**
         * Filter the GitHub repository URL.
         *
         * @param string $url Repository URL.
         */
        return apply_filters('ihumbak_woo_bulk_edit_update_repository_url', self::DEFAULT_REPOSITORY_URL);
    }

    /**
     * Get the GitHub access token.
     *
     * @return string
     */
    public function get_github_access_token(): string
    {
        if (defined('IWBE_GITHUB_ACCESS_TOKEN') && is_string(IWBE_GITHUB_ACCESS_TOKEN)) {
            return IWBE_GITHUB_ACCESS_TOKEN;
        }

        /**
         * Filter the GitHub access token for private repos or higher rate limits.
         *
         * @param string $token GitHub access token. Default empty.
         */
        return apply_filters('ihumbak_woo_bulk_edit_github_access_token', '');
    }

    /**
     * Get the plugin main file path.
     *
     * @return string
     */
    public function get_plugin_file(): string
    {
        if (defined('IWBE_PLUGIN_FILE')) {
            return IWBE_PLUGIN_FILE;
        }

        // Fallback — class is 3 directories deep from plugin root (src/Modules/Updates/).
        return dirname(__DIR__, 3) . '/ihumbak-woo-bulk-edit.php';
    }

    /**
     * Filter update info before it's used.
     *
     * @param object|null $info Update info object.
     * @return object|null Modified update info.
     */
    public function filter_update_info(?object $info): ?object
    {
        if (null === $info) {
            return $info;
        }

        /**
         * Filter the update info object.
         *
         * @param object $info Update info object containing version, download URL, etc.
         */
        return apply_filters('ihumbak_woo_bulk_edit_update_info', $info);
    }

    /**
     * Get the update checker instance.
     *
     * @return object|null
     */
    public function get_update_checker(): ?object
    {
        return $this->update_checker;
    }
}
