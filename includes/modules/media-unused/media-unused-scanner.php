<?php

namespace WPOptiKit\Modules\MediaUnused;

use wpdb;

final class MediaUnusedScanner
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function scan(): array
    {
        $attachments = $this->wpdb->get_results(
            "SELECT ID, guid, post_mime_type
             FROM {$this->wpdb->posts}
             WHERE post_type = 'attachment'
               AND post_mime_type LIKE 'image/%'
               AND post_parent = 0
               AND post_status = 'inherit'
             ORDER BY ID ASC",
            ARRAY_A
        );

        if (empty($attachments)) {
            return array();
        }

        $uploadDir  = wp_get_upload_dir();
        $uploadBase = trailingslashit((string) $uploadDir['basedir']);
        $uploadUrl  = trailingslashit((string) $uploadDir['baseurl']);

        $unused = array();

        foreach ($attachments as $att) {
            $id       = (int) $att['ID'];
            $guid     = (string) $att['guid'];

            /* Check if the attachment URL appears in any post content */
            $inContent = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->wpdb->posts}
                     WHERE post_status IN ('publish', 'draft', 'private', 'pending')
                       AND post_content LIKE %s
                     LIMIT 1",
                    '%' . $this->wpdb->esc_like($guid) . '%'
                )
            );

            if ($inContent > 0) {
                continue;
            }

            /* Check if the attachment URL appears in any meta value */
            $inMeta = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->wpdb->postmeta}
                     WHERE meta_value LIKE %s
                     LIMIT 1",
                    '%' . $this->wpdb->esc_like($guid) . '%'
                )
            );

            if ($inMeta > 0) {
                continue;
            }

            /* Determine the file path from GUID */
            $filePath = str_replace($uploadUrl, $uploadBase, $guid);
            $relPath  = str_replace($uploadBase, '', $filePath);
            $dirName  = dirname($relPath);

            if ($dirName === '.') {
                $dirName = '/';
            }

            if (!file_exists($filePath)) {
                continue;
            }

            $size = (int) filesize($filePath);

            $unused[$dirName][] = array(
                'id'   => $id,
                'name' => basename($filePath),
                'size' => $size,
                'path' => $filePath,
            );
        }

        return $unused;
    }
}
