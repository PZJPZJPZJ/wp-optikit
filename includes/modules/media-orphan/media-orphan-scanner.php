<?php

namespace WPOptiKit\Modules\MediaOrphan;

use wpdb;

final class MediaOrphanScanner
{
    private const ALLOWED_EXTS = array('jpg', 'jpeg', 'png', 'gif', 'webp');

    public function __construct(private readonly wpdb $wpdb)
    {
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function scan(): array
    {
        $uploadDir = wp_get_upload_dir();
        $baseDir   = (string) $uploadDir['basedir'];

        /* --------------------------------------------------
           Step 1: Build known file map from DB attachments
           -------------------------------------------------- */

        $knownFiles = array();

        /* 1a: _wp_attached_file entries (reliable relative paths for ALL attachments) */
        $attachedFiles = $this->wpdb->get_col(
            "SELECT meta_value FROM {$this->wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'"
        );

        foreach ($attachedFiles as $file) {
            $relPath = str_replace('\\', '/', (string) $file);
            $relPath = ltrim($relPath, '/');

            if ($relPath !== '') {
                $knownFiles[$relPath] = true;
            }
        }

        /* 1b: Metadata-based entries (main file + all intermediate sizes + original_image) */
        $attachmentIds = $this->wpdb->get_col(
            "SELECT ID FROM {$this->wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );

        foreach ($attachmentIds as $id) {
            $meta = wp_get_attachment_metadata((int) $id);

            if (!is_array($meta)) {
                continue;
            }

            /* Main file from metadata (e.g. "2025/06/image.jpg") */
            if (!empty($meta['file'])) {
                $knownFiles[(string) $meta['file']] = true;
            }

            /* Intermediate size files — combine directory from main file with each size basename */
            $dir = '';
            if (!empty($meta['file'])) {
                $dir = dirname((string) $meta['file']);
                $dir = ($dir === '.') ? '' : $dir . '/';
            }

            if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
                foreach ($meta['sizes'] as $size) {
                    if (!empty($size['file'])) {
                        $knownFiles[$dir . (string) $size['file']] = true;
                    }
                }
            }

            /* Original image file (WP 5.3+, the pre-scaled original) */
            if (!empty($meta['original_image'])) {
                $knownFiles[$dir . (string) $meta['original_image']] = true;
            }
        }

        /* --------------------------------------------------
           Step 2: Scan disk, filter against known map
           -------------------------------------------------- */

        $orphans = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());

            if (!in_array($ext, self::ALLOWED_EXTS, true)) {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $relPath  = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
            $relPath  = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);

            /* Skip if this file is known to be associated with an attachment */
            if (isset($knownFiles[$relPath])) {
                continue;
            }

            $dirName = dirname($relPath);

            if ($dirName === '.') {
                $dirName = '/';
            }

            $orphans[$dirName][] = array(
                'id'   => 0,
                'name' => $fileInfo->getFilename(),
                'size' => (int) $fileInfo->getSize(),
                'path' => $filePath,
            );
        }

        return $orphans;
    }
}
