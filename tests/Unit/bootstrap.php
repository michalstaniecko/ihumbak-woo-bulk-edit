<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Minimal stubs for WordPress/WooCommerce functions used by unit-testable classes.
// These allow testing field sanitize/validate logic without a full WP environment.

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('wc_format_decimal')) {
    function wc_format_decimal(mixed $number, mixed $dp = false, bool $trim_zeros = false): string
    {
        if ($number === '' || $number === null) {
            return '';
        }

        return (string) (float) $number;
    }
}
