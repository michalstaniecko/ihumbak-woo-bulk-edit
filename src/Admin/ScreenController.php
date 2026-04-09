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
        echo '<div id="ihumbak-woo-bulk-edit-app"></div>';
        echo '</div>';
    }
}
