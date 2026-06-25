<?php

namespace WPOptiKit\Core\Admin;

use WPOptiKit\Core\Compatibility;
use WPOptiKit\Core\Contracts\ModuleInterface;
use WPOptiKit\Core\Queue\JobRepository;
use WPOptiKit\Core\Storage\OptionStore;
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
            'OptiKit',
            'OptiKit',
            'manage_options',
            'wp-optikit',
            array($this, 'render'),
            'dashicons-admin-generic',
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
            'modules'         => $this->moduleSummary(),
            'recent_jobs'     => $this->jobs->getRecentJobs(),
            'environment'     => array(
                'php_current' => PHP_VERSION,
                'php_min'     => Compatibility::MIN_PHP,
                'wp_current'  => get_bloginfo('version'),
                'wp_min'      => Compatibility::MIN_WP,
            ),
            'image_settings'  => $this->options->get(OptionStore::IMAGE_OPTION, ImageSettings::defaults()),
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
