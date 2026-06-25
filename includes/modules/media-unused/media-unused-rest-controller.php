<?php

namespace WPOptiKit\Modules\MediaUnused;

use WP_REST_Request;
use WP_REST_Response;

final class MediaUnusedRestController
{
    public function __construct(private readonly MediaUnusedScanner $scanner)
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
            '/media-unused/scan',
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
            '/media-unused/delete',
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
            $id = (int) ($item['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            if (!current_user_can('delete_post', $id)) {
                $fail++;
                $errors[] = array('id' => $id, 'reason' => 'Permission denied');
                continue;
            }

            if ($mode === 'permanent') {
                $result = wp_delete_attachment($id, true);

                if ($result) {
                    $success++;
                } else {
                    $fail++;
                    $errors[] = array('id' => $id, 'reason' => 'Delete failed');
                }
            } else {
                $result = wp_trash_post($id);

                if ($result) {
                    $success++;
                } else {
                    $fail++;
                    $errors[] = array('id' => $id, 'reason' => 'Trash failed');
                }
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
