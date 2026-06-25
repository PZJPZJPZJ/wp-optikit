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
        $uploadDir  = wp_get_upload_dir();
        $baseDir    = (string) $uploadDir['basedir'];
        $baseUrl    = (string) $uploadDir['baseurl'];

        /* Get all attachment URLs from the database */
        $knownUrls = $this->wpdb->get_col(
            "SELECT guid FROM {$this->wpdb->posts} WHERE post_type = 'attachment'"
        );

        $knownUrls = array_map('strval', $knownUrls);
        $knownMap  = array_flip($knownUrls);

        $orphans = array();

        /* Walk the uploads directory tree */
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $totalFiles = 0;

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());

            if (!in_array($ext, self::ALLOWED_EXTS, true)) {
                continue;
            }

            $totalFiles++;

            $filePath = $fileInfo->getPathname();
            $relPath  = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
            $relPath  = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
            $fileUrl  = trailingslashit($baseUrl) . $relPath;

            /* Skip if the URL is known to the database */
            if (isset($knownMap[$fileUrl])) {
                continue;
            }

            $dirName = dirname($relPath);

            if ($dirName === '.') {
                $dirName = '/';
            }

            $size = (int) $fileInfo->getSize();

            $orphans[$dirName][] = array(
                'id'   => 0,
                'name' => $fileInfo->getFilename(),
                'size' => $size,
                'path' => $filePath,
            );
        }

        return $orphans;
    }

    /**
     * Quick count of all image files in uploads (does not check DB).
     */
    public function countImageFiles(): int
    {
        $baseDir = (string) wp_get_upload_dir()['basedir'];
        $count   = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());

            if (in_array($ext, self::ALLOWED_EXTS, true)) {
                $count++;
            }
        }

        return $count;
    }
}
