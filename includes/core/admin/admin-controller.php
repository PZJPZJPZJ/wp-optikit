<?php

namespace WPOptiKit\Core\Admin;

final class AdminController
{
    public function __construct(private readonly AdminPageRegistry $registry)
    {
    }

    public function boot(): void
    {
        add_action('admin_menu', array($this, 'registerPage'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function registerPage(): void
    {
        add_menu_page(
            __('OptiKit', 'wp-optikit'),
            __('OptiKit', 'wp-optikit'),
            'manage_options',
            'wp-optikit',
            array($this, 'render'),
            'dashicons-performance',
            79
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_wp-optikit') {
            return;
        }

        $cssPath = WPOK_DIR . 'assets/css/admin.css';
        $coreJs  = WPOK_DIR . 'assets/js/core.js';
        $imageJs = WPOK_DIR . 'assets/js/image-module.js';

        wp_enqueue_style('wpok-admin', WPOK_URL . 'assets/css/admin.css', array(), (string) @filemtime($cssPath));
        wp_enqueue_script('wpok-admin-core', WPOK_URL . 'assets/js/core.js', array(), (string) @filemtime($coreJs), true);
        wp_enqueue_script('wpok-admin-image', WPOK_URL . 'assets/js/image-module.js', array('wpok-admin-core'), (string) @filemtime($imageJs), true);

        wp_localize_script(
            'wpok-admin-core',
            'wpokAdmin',
            array(
                'restRoot'  => esc_url_raw(rest_url('wp-optikit/v1/')),
                'restNonce' => wp_create_nonce('wp_rest'),
                'activeTab' => isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview',
                'labels'    => array(
                    // Job & item status labels
                    'statusPending'    => __('Queued', 'wp-optikit'),
                    'statusProcessing' => __('Running', 'wp-optikit'),
                    'statusCancelling' => __('Cancelling', 'wp-optikit'),
                    'statusSucceeded'  => __('Completed', 'wp-optikit'),
                    'statusFailed'     => __('Failed', 'wp-optikit'),
                    'statusCancelled'  => __('Cancelled', 'wp-optikit'),
                    'statusSkipped'    => __('Skipped', 'wp-optikit'),

                    // Core JS
                    'requestFailed'  => __('Request failed', 'wp-optikit'),
                    'btnCancel'      => __('Cancel', 'wp-optikit'),
                    'noActivity'     => __('No queue activity has been recorded yet.', 'wp-optikit'),
                    'tableId'        => __('ID', 'wp-optikit'),
                    'tableType'      => __('Type', 'wp-optikit'),
                    'tableStatus'    => __('Status', 'wp-optikit'),
                    'tableProgress'  => __('Progress', 'wp-optikit'),
                    'tableAction'    => __('Action', 'wp-optikit'),
                    'noAction'       => __('-', 'wp-optikit'),
                    'bytesB'         => __('B', 'wp-optikit'),
                    'bytesKB'        => __('KB', 'wp-optikit'),
                    'bytesMB'        => __('MB', 'wp-optikit'),
                    'bytesGB'        => __('GB', 'wp-optikit'),

                    // Image module
                    'scanning'      => __('Scanning...', 'wp-optikit'),
                    'noAttachments' => __('No matching attachments were found.', 'wp-optikit'),
                    'selectAll'     => __('Select all', 'wp-optikit'),
                    'directories'   => __('directories', 'wp-optikit'),
                    'items'         => __('items', 'wp-optikit'),
                    'itemReady'     => __('ready', 'wp-optikit'),
                    'selectOne'     => __('Select at least one attachment first.', 'wp-optikit'),
                    'jobCreated'    => __('Job created. Waiting for worker.', 'wp-optikit'),
                    'processingMsg' => __('Processing through the background queue.', 'wp-optikit'),
                    'jobFinished'   => __('Job finished with status: %s.', 'wp-optikit'),
                    'cancelReq'     => __('Job cancellation requested.', 'wp-optikit'),
                    'qConvert'      => __('Queue Conversion Job', 'wp-optikit'),
                    'sizeArrow'       => __('->', 'wp-optikit'),
                    'noChange'        => __('No change', 'wp-optikit'),

                    // Cleanup — Orphan Files
                    'noOrphans'       => __('No orphan files were found.', 'wp-optikit'),
                    'deletePermanent' => __('Delete Permanently', 'wp-optikit'),
                    'confirmOrphan'   => __('Permanently delete %d orphan files? This cannot be undone.', 'wp-optikit'),
                    'deleting'        => __('Deleting...', 'wp-optikit'),
                    'deleteResult'    => __('Delete completed.', 'wp-optikit'),
                    'successLower'    => __('succeeded', 'wp-optikit'),
                    'failedLower'     => __('failed', 'wp-optikit'),
                    'regeneratedLower' => __('regenerated', 'wp-optikit'),
                    'totalLower'      => __('total', 'wp-optikit'),

                    // Missing Files
                    'noMissing'           => __('No missing files were found.', 'wp-optikit'),
                    'missingFiles'        => __('missing', 'wp-optikit'),
                    'fileMissing'         => __('missing', 'wp-optikit'),
                    'missingMainFile'     => __('main file missing', 'wp-optikit'),
                    'missingImageSize'    => __('size missing', 'wp-optikit'),
                    'missingOriginalImage' => __('original missing', 'wp-optikit'),
                    'cleanMissingRecords' => __('Clean Missing Records', 'wp-optikit'),
                    'confirmClean'        => __('Confirm Clean', 'wp-optikit'),
                    'deleteMissingRecords' => __('Clean Missing Records', 'wp-optikit'),
                    'confirmMissing'      => __('Clean %d missing records? Only attachments with missing main files will be deleted.', 'wp-optikit'),
                ),
            )
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tabs      = $this->registry->all();
        $activeTab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview';
        $tab       = $this->registry->get($activeTab) ?? reset($tabs);

        $context   = array(
            'tabs'            => $tabs,
            'active_tab'      => $tab,
            'plugin_version'  => WPOK_VERSION,
            'overview'        => $this->prepareOverview(),
        );

        include WPOK_DIR . 'templates/layout.php';
    }

    /** @return array<string, mixed> */
    private function prepareOverview(): array
    {
        /* Server Environment */
        global $wpdb;

        $serverOs       = PHP_OS_FAMILY . ' (' . php_uname('r') . ')';
        $serverSoft     = $_SERVER['SERVER_SOFTWARE'] ?? __('Unknown', 'wp-optikit');
        $mysqlVer       = $wpdb->db_version() ?: __('Unknown', 'wp-optikit');
        $phpVer         = PHP_VERSION;
        $phpMemLimit    = ini_get('memory_limit') ?: __('Unknown', 'wp-optikit');
        $phpMaxInput    = ini_get('max_input_vars') ?: __('Unknown', 'wp-optikit');
        $phpMaxPost     = ini_get('post_max_size') ?: __('Unknown', 'wp-optikit');
        $wpUploadDir    = wp_get_upload_dir();
        $uploadWritable = $wpUploadDir['error'] === false && wp_is_writable($wpUploadDir['basedir']);

        /* WordPress Environment */
        $wpVer          = get_bloginfo('version');
        $siteUrl        = get_site_url();
        $homeUrl        = get_home_url();
        $isMultisite    = is_multisite();
        $maxUploadSize  = size_format(wp_max_upload_size());
        $wpMemLimit     = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : __('Unknown', 'wp-optikit');
        $wpMaxMemLimit  = defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : __('Unknown', 'wp-optikit');
        $permalinkStr   = get_option('permalink_structure');
        $locale         = get_locale();
        $timezone       = wp_timezone_string();
        $adminEmail     = get_option('admin_email');
        $wpDebug        = defined('WP_DEBUG') && WP_DEBUG;

        $extensions = array(
            'exif' => array(
                'loaded'   => extension_loaded('exif'),
                'label'    => 'EXIF',
                'note'     => __('Reads image metadata', 'wp-optikit'),
                'required' => false,
            ),
            'igbinary' => array(
                'loaded'   => extension_loaded('igbinary'),
                'label'    => 'igbinary',
                'note'     => __('Alternative PHP serializer', 'wp-optikit'),
                'required' => false,
            ),
            'imagick' => array(
                'loaded'   => extension_loaded('imagick'),
                'label'    => 'Imagick',
                'note'     => __('Image processing engine', 'wp-optikit'),
                'required' => false,
            ),
            'intl' => array(
                'loaded'   => extension_loaded('intl'),
                'label'    => 'intl',
                'note'     => __('Internationalization support', 'wp-optikit'),
                'required' => false,
            ),
            'zip' => array(
                'loaded'   => extension_loaded('zip'),
                'label'    => 'ZIP',
                'note'     => __('Package operations', 'wp-optikit'),
                'required' => true,
            ),
            'apcu' => array(
                'loaded'   => extension_loaded('apcu'),
                'label'    => 'APCu',
                'note'     => __('Local cache store', 'wp-optikit'),
                'required' => false,
            ),
            'memcached' => array(
                'loaded'   => extension_loaded('memcached'),
                'label'    => 'Memcached',
                'note'     => __('Distributed cache', 'wp-optikit'),
                'required' => false,
            ),
            'opcache' => array(
                'loaded'   => extension_loaded('Zend OPcache'),
                'label'    => 'OPcache',
                'note'     => __('PHP bytecode cache', 'wp-optikit'),
                'required' => false,
            ),
            'redis' => array(
                'loaded'   => extension_loaded('redis'),
                'label'    => 'Redis',
                'note'     => __('Object cache backend', 'wp-optikit'),
                'required' => false,
            ),
            'shmop' => array(
                'loaded'   => extension_loaded('shmop'),
                'label'    => 'shmop',
                'note'     => __('Shared memory operations', 'wp-optikit'),
                'required' => false,
            ),
            'mysqli' => array(
                'loaded'   => extension_loaded('mysqli'),
                'label'    => 'MySQLi',
                'note'     => __('Database driver', 'wp-optikit'),
                'required' => true,
            ),
            'pdo_mysql' => array(
                'loaded'   => extension_loaded('pdo_mysql'),
                'label'    => 'PDO MySQL',
                'note'     => __('Alternative database driver', 'wp-optikit'),
                'required' => false,
            ),
            'gd' => array(
                'loaded'   => extension_loaded('gd'),
                'label'    => 'GD',
                'note'     => __('Image processing fallback', 'wp-optikit'),
                'required' => false,
            ),
        );

        return compact(
            'serverOs',
            'serverSoft',
            'mysqlVer',
            'phpVer',
            'phpMemLimit',
            'phpMaxInput',
            'phpMaxPost',
            'uploadWritable',
            'wpVer',
            'siteUrl',
            'homeUrl',
            'isMultisite',
            'maxUploadSize',
            'wpMemLimit',
            'wpMaxMemLimit',
            'permalinkStr',
            'locale',
            'timezone',
            'adminEmail',
            'wpDebug',
            'extensions'
        );
    }
}
