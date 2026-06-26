<?php

namespace WPOptiKit\Modules\MediaOrphan;

use WP_REST_Request;
use WP_REST_Response;

final class MediaMissingRestController
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
            '/media-missing/scan',
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
            '/media-missing/delete',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'delete'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );
    }

    public function scan(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array('directories' => $this->scanner->scanMissing()));
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $items = $request->get_param('items');

        if (!is_array($items)) {
            return new WP_REST_Response(array('success' => 0, 'fail' => 0, 'errors' => array()), 400);
        }

        $success = 0;
        $fail    = 0;
        $errors  = array();

        /* Collect unique attachment IDs to avoid double-deletion if sizes share an ID */
        $idsToDelete = array();

        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $idsToDelete[$id] = true;
            }
        }

        foreach (array_keys($idsToDelete) as $id) {
            if (!current_user_can('delete_post', $id)) {
                $fail++;
                $errors[] = array('id' => $id, 'reason' => 'Permission denied');
                continue;
            }

            $result = wp_delete_attachment($id, true);

            if ($result) {
                $success++;
            } else {
                $fail++;
                $errors[] = array('id' => $id, 'reason' => 'Delete failed');
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
