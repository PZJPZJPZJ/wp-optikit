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
     * Scan for orphan files — files on disk with no DB record.
     * Only scans YYYY/ directories under uploads (user media only).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function scan(): array
    {
        $uploadDir = wp_get_upload_dir();
        $baseDir   = (string) $uploadDir['basedir'];
        $knownFiles = $this->buildKnownFileMap();
        $orphans    = array();

        foreach ($this->getYearDirectories($baseDir) as $yearDir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($yearDir, \RecursiveDirectoryIterator::SKIP_DOTS),
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
        }

        return $orphans;
    }

    /**
     * Scan for missing files — DB records whose files are missing from disk.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function scanMissing(): array
    {
        $uploadDir = wp_get_upload_dir();
        $baseDir   = (string) $uploadDir['basedir'];

        /* Build the same known file map, but report what's MISSING from disk */
        $missingByDir = array();

        /* 1a: Check _wp_attached_file entries */
        $rows = $this->wpdb->get_results(
            "SELECT p.ID, pm.meta_value
             FROM {$this->wpdb->postmeta} pm
             INNER JOIN {$this->wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_wp_attached_file'
               AND p.post_type = 'attachment'
               AND p.post_mime_type LIKE 'image/%'",
            ARRAY_A
        );

        foreach ($rows as $row) {
            $id      = (int) $row['ID'];
            $relPath = str_replace('\\', '/', ltrim((string) $row['meta_value'], '/'));
            $fullPath = $baseDir . '/' . $relPath;

            if (!file_exists($fullPath)) {
                $dirName = dirname($relPath);
                $missingByDir[$dirName][] = array(
                    'id'           => $id,
                    'name'         => basename($relPath),
                    'size'         => 0,
                    'path'         => $fullPath,
                    'missing'      => true,
                    'missing_type' => 'main',
                );
            }
        }

        /* 1b: Check metadata sizes */
        $attachmentIds = $this->wpdb->get_col(
            "SELECT ID FROM {$this->wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );

        foreach ($attachmentIds as $id) {
            $meta = wp_get_attachment_metadata((int) $id);

            if (!is_array($meta)) {
                continue;
            }

            $dir = '';
            if (!empty($meta['file'])) {
                $dir = dirname((string) $meta['file']);
                $dir = ($dir === '.') ? '' : $dir . '/';
            }

            if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
                foreach ($meta['sizes'] as $sizeName => $size) {
                    if (empty($size['file'])) {
                        continue;
                    }

                    $relPath  = $dir . (string) $size['file'];
                    $fullPath = $baseDir . '/' . $relPath;

                    if (!file_exists($fullPath)) {
                        $dirName = dirname($relPath);
                        $missingByDir[$dirName][] = array(
                            'id'           => (int) $id,
                            'name'         => basename($relPath),
                            'size'         => 0,
                            'path'         => $fullPath,
                            'sizeName'     => $sizeName,
                            'missing'      => true,
                            'missing_type' => 'size',
                        );
                    }
                }
            }

            /* Check original_image */
            if (!empty($meta['original_image'])) {
                $relPath  = $dir . (string) $meta['original_image'];
                $fullPath = $baseDir . '/' . $relPath;

                if (!file_exists($fullPath)) {
                    $dirName = dirname($relPath);
                    $missingByDir[$dirName][] = array(
                        'id'           => (int) $id,
                        'name'         => basename($relPath),
                        'size'         => 0,
                        'path'         => $fullPath,
                        'missing'      => true,
                        'missing_type' => 'original',
                    );
                }
            }
        }

        return $missingByDir;
    }

    public function isReferencedFile(string $filePath): bool
    {
        $uploadDir = wp_get_upload_dir();
        $baseRealPath = realpath((string) $uploadDir['basedir']);

        if ($baseRealPath === false) {
            return false;
        }

        $baseDir   = wp_normalize_path($baseRealPath);
        $path      = wp_normalize_path($filePath);

        if (!str_starts_with($path, trailingslashit($baseDir))) {
            return false;
        }

        $relative = ltrim(substr($path, strlen($baseDir)), '/');

        if ($relative === '') {
            return false;
        }

        return isset($this->buildKnownFileMap()[$relative]);
    }

    /**
     * @return array<string, true>
     */
    private function buildKnownFileMap(): array
    {
        $knownFiles = array();

        /* _wp_attached_file entries for ALL attachments */
        $attachedFiles = $this->wpdb->get_col(
            "SELECT meta_value FROM {$this->wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'"
        );

        foreach ($attachedFiles as $file) {
            $relPath = str_replace('\\', '/', ltrim((string) $file, '/'));
            if ($relPath !== '') {
                $knownFiles[$relPath] = true;
            }
        }

        /* Image metadata: main file + sizes + original_image */
        $attachmentIds = $this->wpdb->get_col(
            "SELECT ID FROM {$this->wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );

        foreach ($attachmentIds as $id) {
            $meta = wp_get_attachment_metadata((int) $id);

            if (!is_array($meta)) {
                continue;
            }

            if (!empty($meta['file'])) {
                $knownFiles[(string) $meta['file']] = true;
            }

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

            if (!empty($meta['original_image'])) {
                $knownFiles[$dir . (string) $meta['original_image']] = true;
            }
        }

        return $knownFiles;
    }

    /**
     * List YYYY directories under the uploads base.
     *
     * @return array<int, string>
     */
    private function getYearDirectories(string $baseDir): array
    {
        $years = array();
        $iterator = new \DirectoryIterator($baseDir);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDir() || $fileInfo->isDot()) {
                continue;
            }
            if (preg_match('/^\d{4}$/', $fileInfo->getFilename())) {
                $years[] = $fileInfo->getPathname();
            }
        }

        sort($years);

        return $years;
    }
}
