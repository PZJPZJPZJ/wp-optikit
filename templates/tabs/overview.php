<?php
$imageSettings = $context['image_settings'];
$imageEnabled  = !empty($imageSettings['enabled']);
$recentJobs    = $context['recent_jobs'];
$runningJobs   = array_filter($recentJobs, static fn (array $job): bool => in_array($job['status'], array('pending', 'processing', 'cancelling'), true));
$completedJobs = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'succeeded');
$failedJobs    = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'failed');

/* Server Environment */
global $wpdb;

$serverOs       = PHP_OS_FAMILY . ' (' . php_uname('r') . ')';
$serverSoft     = $_SERVER['SERVER_SOFTWARE'] ?? __('Unknown', 'wp-optikit');
$mysqlVer       = $wpdb->get_var('SELECT VERSION()') ?? __('Unknown', 'wp-optikit');
$phpVer         = PHP_VERSION;
$phpMemLimit    = ini_get('memory_limit') ?: __('Unknown', 'wp-optikit');
$phpMaxInput    = ini_get('max_input_vars') ?: __('Unknown', 'wp-optikit');
$phpMaxPost     = ini_get('post_max_size') ?: __('Unknown', 'wp-optikit');
$gdInstalled    = extension_loaded('gd');
$zipInstalled   = extension_loaded('zip');

/* Engine Status */
$engineStatus      = $context['engine_status'] ?? array();
$imagickInstalled  = $engineStatus['imagick_loaded'] ?? false;
$imagickWebp       = $engineStatus['imagick_webp'] ?? false;
$animatedWebp      = $engineStatus['animated_webp'] ?? false;
$wpUploadDir    = wp_get_upload_dir();
$uploadWritable = $wpUploadDir['error'] === false && wp_is_writable($wpUploadDir['basedir']);
$elementorDb    = get_option('_elementor_installed_time', null);
$elementorConnected = $elementorDb !== null && class_exists('\Elementor\Plugin');

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
?>
<section class="wpok-overview-hero">
    <div class="wpok-overview-hero-main wpok-card">
        <span class="wpok-overview-kicker"><?php esc_html_e('Platform Status', 'wp-optikit'); ?></span>
        <div class="wpok-platform-modules">
            <div class="wpok-module-card <?php echo $imageEnabled ? 'is-enabled' : 'is-planned'; ?>">
                <h3><?php esc_html_e('Image Format Auto Conversion', 'wp-optikit'); ?></h3>
                <p><?php echo $imageEnabled
                    ? esc_html__('Auto-convert uploads to the selected output format when enabled.', 'wp-optikit')
                    : esc_html__('Disabled. Enable in image settings to auto-convert uploads.', 'wp-optikit'); ?></p>
                <span class="wpok-module-state"><?php echo $imageEnabled
                    ? esc_html__('Enabled', 'wp-optikit')
                    : esc_html__('Disabled', 'wp-optikit'); ?></span>
            </div>
        </div>
    </div>

    <div class="wpok-card wpok-jobs-status-card">
        <span class="wpok-overview-kicker"><?php esc_html_e('Jobs Status', 'wp-optikit'); ?></span>
        <div class="wpok-jobs-status-grid">
            <div class="wpok-job-stat-box wpok-job-stat-green">
                <span class="wpok-job-stat-label"><?php esc_html_e('Completed', 'wp-optikit'); ?></span>
                <strong><?php echo esc_html((string) count($completedJobs)); ?></strong>
                <small><?php esc_html_e('Successful recent runs', 'wp-optikit'); ?></small>
            </div>
            <div class="wpok-job-stat-box wpok-job-stat-yellow">
                <span class="wpok-job-stat-label"><?php esc_html_e('Running jobs', 'wp-optikit'); ?></span>
                <strong><?php echo esc_html((string) count($runningJobs)); ?></strong>
                <small><?php esc_html_e('Active queue operations', 'wp-optikit'); ?></small>
            </div>
            <div class="wpok-job-stat-box wpok-job-stat-red">
                <span class="wpok-job-stat-label"><?php esc_html_e('Recent failures', 'wp-optikit'); ?></span>
                <strong><?php echo esc_html((string) count($failedJobs)); ?></strong>
                <small><?php esc_html_e('Jobs that ended with an error', 'wp-optikit'); ?></small>
            </div>
        </div>
    </div>
</section>

<div class="wpok-grid wpok-grid-overview">
    <section class="wpok-card">
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker"><?php esc_html_e('Server Environment', 'wp-optikit'); ?></span>
                <h2><?php esc_html_e('System & Runtime', 'wp-optikit'); ?></h2>
            </div>
        </div>
        <ul class="wpok-env-list">
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Operating System', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($serverOs); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: Linux', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Software', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($serverSoft); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: nginx or Apache', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">MySQL <?php esc_html_e('Version', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($mysqlVer); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: MySQL 8.0+ or MariaDB 10.4+', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">PHP <?php esc_html_e('Version', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($phpVer); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: PHP 8.1+', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">PHP <?php esc_html_e('Memory Limit', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($phpMemLimit); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: 256M or higher', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">PHP <?php esc_html_e('Max Input Vars', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($phpMaxInput); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: 1000 or higher', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">PHP <?php esc_html_e('Max Post Size', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($phpMaxPost); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: 32M or higher', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">GD <?php esc_html_e('Installed', 'wp-optikit'); ?></span>
                <span class="wpok-env-value <?php echo $gdInstalled ? 'wpok-env-ok' : 'wpok-env-fail'; ?>"><?php echo $gdInstalled ? esc_html__('Yes', 'wp-optikit') : esc_html__('No', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Required for image processing', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">Imagick <?php esc_html_e('Installed', 'wp-optikit'); ?></span>
                <span class="wpok-env-value <?php echo $imagickInstalled ? 'wpok-env-ok' : 'wpok-env-fail'; ?>"><?php echo $imagickInstalled ? esc_html__('Yes', 'wp-optikit') : esc_html__('No', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Required for animated GIF conversion', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Animated WebP (libwebp-anim)', 'wp-optikit'); ?></span>
                <span class="wpok-env-value <?php echo $animatedWebp ? 'wpok-env-ok' : 'wpok-env-fail'; ?>"><?php echo $animatedWebp ? esc_html__('Yes', 'wp-optikit') : esc_html__('No', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Enables animated GIF to animated WebP conversion', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">ZIP <?php esc_html_e('Installed', 'wp-optikit'); ?></span>
                <span class="wpok-env-value <?php echo $zipInstalled ? 'wpok-env-ok' : 'wpok-env-fail'; ?>"><?php echo $zipInstalled ? esc_html__('Yes', 'wp-optikit') : esc_html__('No', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Required for package operations', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Write Permissions', 'wp-optikit'); ?></span>
                <span class="wpok-env-value <?php echo $uploadWritable ? 'wpok-env-ok' : 'wpok-env-fail'; ?>"><?php echo $uploadWritable ? esc_html__('All Right', 'wp-optikit') : esc_html__('Not Writable', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Required for file operations', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label">Elementor <?php esc_html_e('Library', 'wp-optikit'); ?></span>
                <span class="wpok-env-value <?php echo $elementorConnected ? 'wpok-env-ok' : 'wpok-env-fail'; ?>"><?php echo $elementorConnected ? esc_html__('Connected', 'wp-optikit') : esc_html__('Not Connected', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Required for Elementor integration', 'wp-optikit'); ?></small>
            </li>
        </ul>
    </section>

    <section class="wpok-card">
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker"><?php esc_html_e('WordPress Environment', 'wp-optikit'); ?></span>
                <h2><?php esc_html_e('Site & Configuration', 'wp-optikit'); ?></h2>
            </div>
        </div>
        <ul class="wpok-env-list">
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Version', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($wpVer); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: 6.7+', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Site URL', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($siteUrl); ?></span>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Home URL', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($homeUrl); ?></span>
            </li>
            <li>
                <span class="wpok-env-label">WP <?php esc_html_e('Multisite', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo $isMultisite ? esc_html__('Yes', 'wp-optikit') : esc_html__('No', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: No (for this setup)', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Max Upload Size', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($maxUploadSize); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: 32 MB or higher', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Memory Limit', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($wpMemLimit); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: 256M or higher', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Max Memory Limit', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($wpMaxMemLimit); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: 256M or higher', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Permalink Structure', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo $permalinkStr ? esc_html($permalinkStr) : esc_html__('Plain', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: /%postname%/', 'wp-optikit'); ?></small>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Language', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($locale); ?></span>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Timezone', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($timezone); ?></span>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Admin Email', 'wp-optikit'); ?></span>
                <span class="wpok-env-value"><?php echo esc_html($adminEmail); ?></span>
            </li>
            <li>
                <span class="wpok-env-label"><?php esc_html_e('Debug Mode', 'wp-optikit'); ?></span>
                <span class="wpok-env-value <?php echo $wpDebug ? 'wpok-env-fail' : 'wpok-env-ok'; ?>"><?php echo $wpDebug ? esc_html__('Active', 'wp-optikit') : esc_html__('Inactive', 'wp-optikit'); ?></span>
                <small class="wpok-env-rec"><?php esc_html_e('Recommended: Inactive on production', 'wp-optikit'); ?></small>
            </li>
        </ul>
    </section>
</div>
