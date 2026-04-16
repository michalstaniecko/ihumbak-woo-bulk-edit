<?php

declare(strict_types=1);

// Disable update checker in tests to avoid PucFactory initialization issues.
if (! defined('IWBE_DISABLE_UPDATES')) {
    define('IWBE_DISABLE_UPDATES', true);
}

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

if (! function_exists('absint')) {
    function absint(mixed $maybeint): int
    {
        return abs((int) $maybeint);
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

if (! function_exists('is_taxonomy_hierarchical')) {
    /**
     * Stub: returns true only for product_cat to mimic WooCommerce default.
     */
    function is_taxonomy_hierarchical(string $taxonomy): bool
    {
        return $taxonomy === 'product_cat';
    }
}

if (! function_exists('get_term_children')) {
    /**
     * Stub: no child terms in unit-test environment.
     *
     * @return array<int, int>
     */
    function get_term_children(int $termId, string $taxonomy): array
    {
        return [];
    }
}

// ── WP_Error stub ──────────────────────────────────────────────────────────
if (! class_exists('WP_Error')) {
    /**
     * Minimal WP_Error stub for unit tests.
     */
    class WP_Error
    {
        /** @var array<string, array<int, string>> */
        private array $errors = [];

        /** @var array<string, mixed> */
        private array $errorData = [];

        /**
         * @param array<string, mixed> $data
         */
        public function __construct(
            string $code = '',
            string $message = '',
            mixed $data = ''
        ) {
            if ($code !== '') {
                $this->errors[$code][] = $message;
                $this->errorData[$code] = $data;
            }
        }

        public function get_error_message(string $code = ''): string
        {
            if ($code === '') {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_code(): string
        {
            return array_key_first($this->errors) ?? '';
        }

        /**
         * @return mixed
         */
        public function get_error_data(string $code = ''): mixed
        {
            if ($code === '') {
                $code = $this->get_error_code();
            }
            return $this->errorData[$code] ?? null;
        }
    }
}

// ── wpdb stub ─────────────────────────────────────────────────────────────
// Provides a minimal global $wpdb for unit tests that need to build SQL
// fragments (taxonomy subqueries). Does NOT execute queries.
if (! isset($GLOBALS['wpdb'])) {
    /**
     * Minimal wpdb stub for unit tests.
     *
     * Only `prepare()`, `esc_like()`, and the table-name properties
     * are implemented. `get_var()` / `get_results()` are not available
     * because unit tests only inspect the generated SQL, not its results.
     */
    $GLOBALS['wpdb'] = new class {
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public string $term_relationships = 'wp_term_relationships';
        public string $term_taxonomy = 'wp_term_taxonomy';
        public string $terms = 'wp_terms';

        /**
         * Simulate $wpdb->prepare(): replaces %s / %d placeholders with values.
         *
         * Returns null when placeholder count does not match value count,
         * mirroring WordPress's behavior when called incorrectly.
         *
         * @param mixed ...$args Values for each placeholder.
         */
        public function prepare(string $sql, mixed ...$args): ?string
        {
            preg_match_all('/%[sdf]/', $sql, $m);
            $placeholderCount = count($m[0]);
            $valueCount       = count($args);

            if ($placeholderCount !== $valueCount) {
                // Mimic WordPress: return null for mismatched placeholders.
                return null;
            }

            $i = 0;
            return (string) preg_replace_callback(
                '/%[sdf]/',
                function (array $match) use ($args, &$i): string {
                    $v = $args[$i++] ?? '';
                    return match ($match[0]) {
                        '%d'    => (string) (int) $v,
                        '%f'    => (string) (float) $v,
                        default => "'" . addslashes((string) $v) . "'",
                    };
                },
                $sql
            );
        }

        /**
         * Escape a string for use in a LIKE clause.
         */
        public function esc_like(string $text): string
        {
            return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $text);
        }
    };
}
