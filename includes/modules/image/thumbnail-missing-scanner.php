<?php

namespace WPOptiKit\Modules\Image;

use WPOptiKit\Core\Support\SizeFormatter;
use wpdb;

final class ThumbnailMissingScanner
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function scan(): array
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $rows = $this->wpdb->get_results(
            "SELECT ID, post_mime_type FROM {$this->wpdb->posts} WHERE post_type = 'attachment'",
            ARRAY_A
        );

        $grouped = array();

        foreach ($rows as $row) {
            $item = $this->inspectAttachment((int) $row['ID'], (string) $row['post_mime_type']);

            if ($item === null) {
                continue;
            }

            $grouped[(string) $item['path']][] = $item;
        }

        ksort($grouped);

        return $grouped;
    }

    private function inspectAttachment(int $attachmentId, string $mimeType): ?array
    {
        $filePath = get_attached_file($attachmentId);

        if (!is_string($filePath) || $filePath === '') {
            return null;
        }

        $metadata = wp_get_attachment_metadata($attachmentId);
        $metadata = is_array($metadata) ? $metadata : array();
        $relative = $this->relativePathForAttachment($filePath, $metadata);
        $directory = dirname($relative);
        $directory = $directory !== '' && $directory !== '.' ? $directory : 'Root directory';
        $fileExists = file_exists($filePath);
        $attachmentType = str_starts_with($mimeType, 'image/') ? 'image' : ($mimeType === 'application/pdf' ? 'pdf' : '');

        if ($attachmentType === '') {
            return null;
        }

        if (!$fileExists) {
            return $this->buildItem(
                $attachmentId,
                $filePath,
                $directory,
                $attachmentType,
                array(),
                'main_missing'
            );
        }

        if ($attachmentType === 'image') {
            $missing = $this->missingImageSizes($attachmentId, $filePath, $metadata);

            if (empty($missing)) {
                return null;
            }

            return $this->buildItem($attachmentId, $filePath, $directory, 'image', $missing);
        }

        if ($attachmentType === 'pdf') {
            $missing = $this->missingPdfPreviewSizes($filePath, $metadata);

            if (empty($missing)) {
                return null;
            }

            return $this->buildItem($attachmentId, $filePath, $directory, 'pdf', $missing);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function missingImageSizes(int $attachmentId, string $filePath, array $metadata): array
    {
        $missing = array();

        foreach ($this->missingRecordedSizeFiles($filePath, $metadata) as $sizeName) {
            $missing[$sizeName] = true;
        }

        if (function_exists('wp_get_missing_image_subsizes')) {
            foreach (array_keys(wp_get_missing_image_subsizes($attachmentId)) as $sizeName) {
                $missing[(string) $sizeName] = true;
            }
        }

        return array_keys($missing);
    }

    /**
     * @return array<int, string>
     */
    private function missingPdfPreviewSizes(string $filePath, array $metadata): array
    {
        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return array('pdf-preview');
        }

        return $this->missingRecordedSizeFiles($filePath, $metadata);
    }

    /**
     * @return array<int, string>
     */
    private function missingRecordedSizeFiles(string $filePath, array $metadata): array
    {
        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return array();
        }

        $missing = array();
        $directory = $this->metadataDirectory($filePath, $metadata);

        foreach ($metadata['sizes'] as $sizeName => $size) {
            if (empty($size['file'])) {
                continue;
            }

            if (!file_exists($directory . '/' . (string) $size['file'])) {
                $missing[] = (string) $sizeName;
            }
        }

        return $missing;
    }

    private function metadataDirectory(string $filePath, array $metadata): string
    {
        if (!empty($metadata['file'])) {
            $uploadDir = wp_get_upload_dir();

            return wp_normalize_path((string) $uploadDir['basedir'] . '/' . dirname((string) $metadata['file']));
        }

        return wp_normalize_path(dirname($filePath));
    }

    private function relativePathForAttachment(string $filePath, array $metadata): string
    {
        if (!empty($metadata['file'])) {
            return str_replace('\\', '/', ltrim((string) $metadata['file'], '/'));
        }

        $uploadDir = wp_get_upload_dir();
        $baseDir   = wp_normalize_path((string) $uploadDir['basedir']);
        $path      = wp_normalize_path($filePath);

        if (str_starts_with($path, trailingslashit($baseDir))) {
            return ltrim(substr($path, strlen($baseDir)), '/');
        }

        return basename($filePath);
    }

    /**
     * @param array<int, string> $missingSizes
     * @return array<string, mixed>
     */
    private function buildItem(
        int $attachmentId,
        string $filePath,
        string $directory,
        string $attachmentType,
        array $missingSizes,
        string $issueType = 'missing_thumbnails'
    ): array {
        $fileSize = file_exists($filePath) ? (int) filesize($filePath) : 0;

        return array(
            'id'             => $attachmentId,
            'name'           => basename($filePath),
            'size'           => $fileSize,
            'size_formatted' => SizeFormatter::formatBytes($fileSize),
            'path'           => $directory,
            'attachment_type' => $attachmentType,
            'missing_count'  => count($missingSizes),
            'missing_sizes'  => array_values($missingSizes),
            'issue_type'     => $issueType,
        );
    }
}
