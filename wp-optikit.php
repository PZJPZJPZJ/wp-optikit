<?php
/**
 * Plugin Name: WP OptiKit
 * Description: All-in-one WordPress Speed Optimization Toolkit
 * Version: 2.1.0
 * Author: AzzDev
 * Requires PHP: 8.1
 * Requires at least: 6.7
 * Text Domain: wp-optikit
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPOK_VERSION', get_file_data(__FILE__, ['version' => 'Version'])['version']);
define('WPOK_FILE', __FILE__);
define('WPOK_DIR', plugin_dir_path(__FILE__));
define('WPOK_URL', plugin_dir_url(__FILE__));

require_once WPOK_DIR . 'includes/core/bootstrap.php';

register_activation_hook(__FILE__, array('WPOptiKit\Core\Bootstrap', 'activate'));

if (!defined('WP_SANDBOX_SCRAPING') || !WP_SANDBOX_SCRAPING) {
    WPOptiKit\Core\Bootstrap::boot(__FILE__);
}
