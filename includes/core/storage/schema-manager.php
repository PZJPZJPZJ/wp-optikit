<?php

namespace WPOptiKit\Core\Storage;

use WPOptiKit\Modules\Image\ImageSettings;

final class SchemaManager
{
    public function __construct(private readonly OptionStore $options)
    {
    }

    public function activate(): void
    {
        $this->createTables();
        $this->seedOptions();
    }

    private function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $jobsTable      = $wpdb->prefix . 'wpok_jobs';
        $itemsTable     = $wpdb->prefix . 'wpok_job_items';

        $jobsSql = "CREATE TABLE {$jobsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module varchar(50) NOT NULL,
            job_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            payload_json longtext NULL,
            total_items int(11) NOT NULL DEFAULT 0,
            processed_items int(11) NOT NULL DEFAULT 0,
            failed_items int(11) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY module_status (module, status),
            KEY job_type_status (job_type, status)
        ) {$charsetCollate};";

        $itemsSql = "CREATE TABLE {$itemsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_path text NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempt_count int(11) NOT NULL DEFAULT 0,
            result_json longtext NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY job_status (job_id, status),
            KEY object_lookup (object_type, object_id)
        ) {$charsetCollate};";

        dbDelta($jobsSql);
        dbDelta($itemsSql);
    }

    private function seedOptions(): void
    {
        add_option(
            OptionStore::CORE_OPTION,
            array(
                'legacy_notice_dismissed' => false,
            ),
            '',
            false
        );

        add_option(OptionStore::IMAGE_OPTION, ImageSettings::defaults(), '', false);
        add_option(OptionStore::CACHE_OPTION, array('enabled' => false, 'status' => 'planned'), '', false);
        add_option(OptionStore::ASSETS_OPTION, array('enabled' => false, 'status' => 'planned'), '', false);
        add_option(OptionStore::DATABASE_OPTION, array('enabled' => false, 'status' => 'planned'), '', false);
    }
}
