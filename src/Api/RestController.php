<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Api;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;

/**
 * Base REST controller with shared utilities.
 */
abstract class RestController extends WP_REST_Controller
{
    protected const NAMESPACE = 'ihumbak-woo-bulk-edit/v1';

    /**
     * @var string REST resource name, set by child classes.
     */
    protected $rest_base = '';

    /**
     * Create a standardized error response.
     */
    protected function error(string $code, string $message, int $status = 400, array $data = []): WP_Error
    {
        $data['status'] = $status;

        return new WP_Error($code, $message, $data);
    }

    /**
     * Create a success response with data.
     */
    protected function success(mixed $data = null, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response($data, $status);
    }

    /**
     * Get the full namespace for route registration.
     */
    protected function getNamespace(): string
    {
        return self::NAMESPACE;
    }
}
