<?php

namespace WPOptiKit\Modules\MediaOrphan;

use WP_REST_Request;
use WP_REST_Response;

final class MediaOrphanRestController
{
    public function __construct(private readonly MediaOrphanScanner $scanner)
    {
    }

    public function boot(): void
    {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'wp-optikit/v1',
            '/media-orphan/count',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'count'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/media-orphan/scan',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'scan'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/media-orphan/delete',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'delete'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );
    }

    public function count(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array('total' => $this->scanner->countImageFiles()));
    }

    public function scan(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array('directories' => $this->scanner->scan()));
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $mode  = $request->get_param('mode');
        $items = $request->get_param('items');

        if (!is_array($items)) {
            return new WP_REST_Response(array('success' => 0, 'fail' => 0, 'errors' => array()), 400);
        }

        $success = 0;
        $fail    = 0;
        $errors  = array();

        foreach ($items as $item) {
            $filePath = (string) ($item['file'] ?? '');

            if ($filePath === '') {
                continue;
            }

            if (!current_user_can('manage_options')) {
                $fail++;
                $errors[] = array('file' => $filePath, 'reason' => 'Permission denied');
                continue;
            }

            /* Security: only allow files inside wp-content/uploads */
            $uploadDir = wp_get_upload_dir();
            $baseDir   = realpath((string) $uploadDir['basedir']);

            if ($baseDir === false) {
                $fail++;
                $errors[] = array('file' => $filePath, 'reason' => 'Upload dir not found');
                continue;
            }

            $fullPath = realpath($filePath);

            if ($fullPath === false || strpos($fullPath, $baseDir) !== 0) {
                $fail++;
                $errors[] = array('file' => $filePath, 'reason' => 'File outside uploads directory');
                continue;
            }

            if (!file_exists($fullPath)) {
                $fail++;
                $errors[] = array('file' => $filePath, 'reason' => 'File not found');
                continue;
            }

            if (@unlink($fullPath)) {
                $success++;
            } else {
                $fail++;
                $errors[] = array('file' => $filePath, 'reason' => 'Unable to delete file');
            }
        }

        return new WP_REST_Response(array(
            'success' => $success,
            'fail'    => $fail,
            'errors'  => $errors,
        ));
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
