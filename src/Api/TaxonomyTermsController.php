<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use IhumbakWooBulkEdit\Fields\TaxonomyMap;
use IhumbakWooBulkEdit\Security\CapabilityChecker;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for fetching taxonomy terms (for the TaxonomyTermPicker combobox).
 *
 * Route: GET /ihumbak-woo-bulk-edit/v1/taxonomies/{field_key}/terms
 *
 * Query params:
 *   search   (string)         — filter terms by name (substring match)
 *   per_page (int, 1-100)     — number of terms to return, default 30
 *   include  (CSV of int IDs) — fetch specific term IDs regardless of search/per_page
 *
 * Response: { "items": [...], "total": int }
 *
 * Error for unknown field_key: 404, code wbm_invalid_taxonomy_field
 *
 * @license GPL-2.0-or-later
 */
final class TaxonomyTermsController extends RestController
{
    protected $rest_base = 'taxonomies';

    public function __construct(
        private readonly CapabilityChecker $capabilityChecker,
    ) {}

    public function register_routes(): void
    {
        register_rest_route(
            $this->getNamespace(),
            '/' . $this->rest_base . '/(?P<field_key>[a-z0-9_\-]+)/terms',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this->capabilityChecker, 'permissionRead'],
                'args'                => $this->getArgs(),
            ]
        );
    }

    /**
     * GET /taxonomies/{field_key}/terms
     *
     * Signature is compatible with WP_REST_Controller::get_items (no typed param).
     *
     * @param WP_REST_Request $request
     */
    public function get_items( $request ): WP_REST_Response|WP_Error
    {
        $fieldKey = (string) $request->get_param('field_key');

        if (! TaxonomyMap::isTaxonomyField($fieldKey)) {
            return $this->error(
                'wbm_invalid_taxonomy_field',
                sprintf(
                    /* translators: %s: field key */
                    __('"%s" is not a valid taxonomy field.', 'ihumbak-woo-bulk-edit'),
                    $fieldKey
                ),
                404
            );
        }

        $taxonomy = TaxonomyMap::taxonomyForField($fieldKey);

        if ($taxonomy === null) {
            // Defensive: should not happen if isTaxonomyField passed.
            return $this->error(
                'wbm_invalid_taxonomy_field',
                __('Taxonomy not found.', 'ihumbak-woo-bulk-edit'),
                404
            );
        }

        $includeParam = (string) ($request->get_param('include') ?? '');
        $includeIds   = $this->parseIncludeIds($includeParam);

        if (! empty($includeIds)) {
            return $this->fetchByIds($taxonomy, $includeIds);
        }

        $search  = sanitize_text_field((string) ($request->get_param('search') ?? ''));
        $perPage = (int) ($request->get_param('per_page') ?? 30);
        $perPage = max(1, min(100, $perPage));

        return $this->fetchBySearch($taxonomy, $search, $perPage);
    }

    /**
     * Fetch terms by specific IDs — bypasses search and per_page.
     *
     * @param list<int> $ids
     */
    private function fetchByIds(string $taxonomy, array $ids): WP_REST_Response
    {
        $args = [
            'taxonomy'   => $taxonomy,
            'include'    => $ids,
            'hide_empty' => false,
            'orderby'    => 'include',
        ];

        $terms = get_terms($args);

        if (is_wp_error($terms) || ! is_array($terms)) {
            $terms = [];
        }

        // Count total via a separate call (include bypasses number).
        $total = count($terms);

        return $this->success([
            'items' => array_values(array_map([$this, 'formatTerm'], $terms)),
            'total' => $total,
        ]);
    }

    /**
     * Fetch terms by search string with per_page limit.
     */
    private function fetchBySearch(string $taxonomy, string $search, int $perPage): WP_REST_Response
    {
        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => $perPage,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        if ($search !== '') {
            $args['search'] = $search;
        }

        $terms = get_terms($args);

        if (is_wp_error($terms) || ! is_array($terms)) {
            $terms = [];
        }

        // Fetch total count for this search (ignores 'number').
        $countArgs = $args;
        $countArgs['fields'] = 'count';
        unset($countArgs['number']);

        $total = (int) get_terms($countArgs);

        return $this->success([
            'items' => array_values(array_map([$this, 'formatTerm'], $terms)),
            'total' => $total,
        ]);
    }

    /**
     * Serialize a WP_Term object into an array safe for JSON output.
     *
     * @param \WP_Term $term
     * @return array{id: int, name: string, slug: string, count: int, parent: int}
     */
    private function formatTerm(\WP_Term $term): array
    {
        return [
            'id'     => (int) $term->term_id,
            'name'   => (string) $term->name,
            'slug'   => (string) $term->slug,
            'count'  => (int) $term->count,
            'parent' => (int) $term->parent,
        ];
    }

    /**
     * Parse a comma-separated string of IDs into a list of positive integers.
     *
     * @return list<int>
     */
    private function parseIncludeIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>
     */
    private function getArgs(): array
    {
        return [
            'field_key' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
            ],
            'search' => [
                'type'              => 'string',
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'per_page' => [
                'type'     => 'integer',
                'required' => false,
                'default'  => 30,
                'minimum'  => 1,
                // No maximum here — clamping to 100 is done in get_items().
            ],
            'include' => [
                'type'     => 'string',
                'required' => false,
                'default'  => '',
            ],
        ];
    }
}
