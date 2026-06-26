<?php

namespace WPOptiKit\Core\Admin;

use WPOptiKit\Core\Compatibility;
use WPOptiKit\Core\Contracts\ModuleInterface;
use WPOptiKit\Core\Queue\JobRepository;
use WPOptiKit\Core\Storage\OptionStore;
use WPOptiKit\Modules\Image\ImageProcessor;
use WPOptiKit\Modules\Image\ImageSettings;

final class AdminController
{
    /**
     * @param array<string, ModuleInterface> $modules
     */
    public function __construct(
        private readonly AdminPageRegistry $registry,
        private readonly array $modules,
        private readonly OptionStore $options,
        private readonly JobRepository $jobs
    ) {
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
                    'btnCancelling'  => __('Cancelling...', 'wp-optikit'),
                    'btnCancel'      => __('Cancel', 'wp-optikit'),
                    'noActivity'     => __('No queue activity has been recorded yet.', 'wp-optikit'),
                    'tableId'        => __('ID', 'wp-optikit'),
                    'tableModule'    => __('Module', 'wp-optikit'),
                    'tableType'      => __('Type', 'wp-optikit'),
                    'tableStatus'    => __('Status', 'wp-optikit'),
                    'tableProgress'  => __('Progress', 'wp-optikit'),
                    'tableUpdated'   => __('Updated', 'wp-optikit'),
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
                    'qRecompress'   => __('Queue Re-compression Job', 'wp-optikit'),
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
                    'totalLower'      => __('total', 'wp-optikit'),

                    // Missing Files
                    'noMissing'           => __('No missing files were found.', 'wp-optikit'),
                    'missingFiles'        => __('missing', 'wp-optikit'),
                    'fileMissing'         => __('missing', 'wp-optikit'),
                    'deleteMissingRecords' => __('Delete Attachment Records', 'wp-optikit'),
                    'confirmMissing'      => __('Delete %d attachment records? Files are already missing from disk.', 'wp-optikit'),
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

        // 计算图片引擎状态
        $imageSettings = new ImageSettings($this->options);
        $imageProcessor = new ImageProcessor($imageSettings);
        $engineStatus   = $imageProcessor->getEngineStatus();

        $context   = array(
            'tabs'            => $tabs,
            'active_tab'      => $tab,
            'plugin_version'  => WPOK_VERSION,
            'modules'         => $this->moduleSummary(),
            'recent_jobs'     => $this->jobs->getRecentJobs(),
            'environment'     => array(
                'php_current' => PHP_VERSION,
                'php_min'     => Compatibility::MIN_PHP,
                'wp_current'  => get_bloginfo('version'),
                'wp_min'      => Compatibility::MIN_WP,
            ),
            'image_settings'  => $this->options->get(OptionStore::IMAGE_OPTION, ImageSettings::defaults()),
            'engine_status'   => $engineStatus,
        );

        include WPOK_DIR . 'templates/layout.php';
    }

    private function moduleSummary(): array
    {
        $summary = array();

        foreach ($this->modules as $module) {
            $summary[] = array(
                'id'      => $module->get_id(),
                'label'   => $module->get_label(),
                'enabled' => $module->is_enabled(),
            );
        }

        return $summary;
    }
}
