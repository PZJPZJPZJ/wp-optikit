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

        $idsToDelete = array();
        $metadataCleanup = array();

        foreach ($items as $item) {
            $id   = (int) ($item['id'] ?? 0);
            $type = sanitize_key((string) ($item['type'] ?? ''));

            if ($id <= 0 || $type === '') {
                continue;
            }

            if ($type === 'main') {
                $idsToDelete[$id] = true;
                continue;
            }

            if (!isset($metadataCleanup[$id])) {
                $metadataCleanup[$id] = array(
                    'sizes'    => array(),
                    'original' => false,
                );
            }

            if ($type === 'size') {
                $sizeName = sanitize_key((string) ($item['sizeName'] ?? ''));

                if ($sizeName !== '') {
                    $metadataCleanup[$id]['sizes'][$sizeName] = true;
                }
            } elseif ($type === 'original') {
                $metadataCleanup[$id]['original'] = true;
            }
        }

        foreach (array_keys($idsToDelete) as $id) {
            if (!current_user_can('delete_post', $id)) {
                $fail++;
                $errors[] = array('id' => $id, 'type' => 'main', 'reason' => 'Permission denied');
                continue;
            }

            $attachedFile = get_attached_file((int) $id);

            if (is_string($attachedFile) && file_exists($attachedFile)) {
                $fail++;
                $errors[] = array('id' => $id, 'type' => 'main', 'reason' => 'Attachment file exists');
                continue;
            }

            $result = wp_delete_attachment($id, true);

            if ($result) {
                $success++;
            } else {
                $fail++;
                $errors[] = array('id' => $id, 'type' => 'main', 'reason' => 'Delete failed');
            }
        }

        foreach ($metadataCleanup as $id => $cleanup) {
            if (isset($idsToDelete[$id])) {
                continue;
            }

            if (!current_user_can('edit_post', (int) $id)) {
                $fail++;
                $errors[] = array('id' => (int) $id, 'reason' => 'Permission denied');
                continue;
            }

            $cleaned = $this->cleanMissingMetadata((int) $id, $cleanup);

            if ($cleaned) {
                $success++;
            } else {
                $fail++;
                $errors[] = array('id' => (int) $id, 'reason' => 'Metadata cleanup failed');
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

    /**
     * @param array{sizes: array<string, true>, original: bool} $cleanup
     */
    private function cleanMissingMetadata(int $attachmentId, array $cleanup): bool
    {
        $metadata = wp_get_attachment_metadata($attachmentId);

        if (!is_array($metadata)) {
            return false;
        }

        $changed = false;
        $directory = '';

        if (!empty($metadata['file'])) {
            $directory = dirname((string) $metadata['file']);
            $directory = ($directory === '.') ? '' : $directory . '/';
        }

        $uploadDir = wp_get_upload_dir();
        $baseDir   = (string) $uploadDir['basedir'];

        foreach (array_keys((array) ($cleanup['sizes'] ?? array())) as $sizeName) {
            if (empty($metadata['sizes'][$sizeName]['file'])) {
                continue;
            }

            $relativePath = $directory . (string) $metadata['sizes'][$sizeName]['file'];
            $fullPath     = $baseDir . '/' . $relativePath;

            if (!file_exists($fullPath)) {
                unset($metadata['sizes'][$sizeName]);
                $changed = true;
            }
        }

        if (!empty($cleanup['original']) && !empty($metadata['original_image'])) {
            $relativePath = $directory . (string) $metadata['original_image'];
            $fullPath     = $baseDir . '/' . $relativePath;

            if (!file_exists($fullPath)) {
                unset($metadata['original_image']);
                $changed = true;
            }
        }

        if (!$changed) {
            return true;
        }

        return wp_update_attachment_metadata($attachmentId, $metadata) !== false;
    }
}
