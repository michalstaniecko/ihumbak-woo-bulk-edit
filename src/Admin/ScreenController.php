<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Admin;

/**
 * Renders the admin page that hosts the React application.
 */
final class ScreenController
{
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Bulk Edit Products', 'ihumbak-woo-bulk-edit' ) . '</h1>';
        echo '<hr class="wp-header-end">';
        echo '<div id="ihumbak-woo-bulk-edit-app"></div>';
        echo '</div>';
    }
}
