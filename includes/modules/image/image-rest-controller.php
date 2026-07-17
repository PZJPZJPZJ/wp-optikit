<?php

namespace WPOptiKit\Modules\Image;

use WP_REST_Request;
use WP_REST_Response;

final class ImageRestController
{
    public function __construct(
        private readonly ImageScanner $scanner,
        private readonly ThumbnailMissingScanner $thumbnailScanner
    ) {
    }

    public function boot(): void
    {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'wp-optikit/v1',
            '/images/scans/non-webp',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'scanNonWebp'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/images/scans/oversized',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'scanOversized'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/thumbnail-missing/scan',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'scanMissingThumbnails'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );
    }

    public function scanNonWebp(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array('directories' => $this->scanner->scanNonWebpImages()));
    }

    public function scanOversized(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array('directories' => $this->scanner->scanOversizedImages()));
    }

    public function scanMissingThumbnails(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array('directories' => $this->thumbnailScanner->scan()));
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
