<?php

namespace WPOptiKit\Modules\Image;

use WPOptiKit\Core\Support\SizeFormatter;

final class ImageScanner
{
    public function __construct(private readonly ImageSettings $settings)
    {
    }

    public function scanNonWebpImages(): array
    {
        $query = new \WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => $this->allowedMimeTypes(),
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );

        return $this->groupAttachments($query->posts, false);
    }

    public function scanOversizedImages(): array
    {
        $query = new \WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );

        return $this->groupAttachments($query->posts, true);
    }

    private function groupAttachments(array $attachmentIds, bool $oversizedOnly): array
    {
        $grouped    = array();
        $uploadDir  = wp_upload_dir();
        $baseDir    = wp_normalize_path((string) $uploadDir['basedir']);
        $maxSize    = $this->settings->getMaxFileSizeBytes();

        foreach ($attachmentIds as $attachmentId) {
            $filePath = get_attached_file((int) $attachmentId);

            if (!is_string($filePath) || !file_exists($filePath)) {
                continue;
            }

            $fileSize = (int) filesize($filePath);

            if ($oversizedOnly) {
                if ($fileSize <= $maxSize) {
                    continue;
                }
            } else {
                $webpPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $filePath);

                if (is_string($webpPath) && file_exists($webpPath)) {
                    continue;
                }
            }

            $normalized = wp_normalize_path($filePath);
            $relative   = dirname(str_replace($baseDir, '', $normalized));
            $relative   = ltrim($relative, '/');
            $directory  = $relative !== '' && $relative !== '.' ? $relative : 'Root directory';

            $grouped[$directory][] = array(
                'id'             => (int) $attachmentId,
                'name'           => basename($filePath),
                'size'           => $fileSize,
                'size_formatted' => SizeFormatter::formatBytes($fileSize),
                'path'           => $directory,
            );
        }

        ksort($grouped);

        return $grouped;
    }

    private function allowedMimeTypes(): array
    {
        $map   = array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        );
        $mimes = array();

        foreach ($this->settings->getFormats() as $format) {
            if (isset($map[$format])) {
                $mimes[] = $map[$format];
            }
        }

        return $mimes;
    }
}
