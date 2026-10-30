<?php
/** @var array<string, mixed> $context */
$overview = $context['overview'];
?>
<section class="wpok-section" aria-labelledby="wpok-environment-title">
    <header class="wpok-section-heading">
        <h2 id="wpok-environment-title"><?php esc_html_e('Environment Information', 'wp-optikit'); ?></h2>
        <p><?php esc_html_e('Review the server runtime, PHP extensions, and WordPress configuration.', 'wp-optikit'); ?></p>
    </header>
    <div class="wpok-grid">
        <section class="wpok-card">
            <div class="wpok-card-heading">
                <div>
                    <h3><?php esc_html_e('System & Runtime', 'wp-optikit'); ?></h3>
                </div>
            </div>
            <ul class="wpok-env-list">
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Operating System', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['serverOs']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: Linux', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Software', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['serverSoft']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: nginx or Apache', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label">MySQL <?php esc_html_e('Version', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['mysqlVer']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: MySQL 8.0+ or MariaDB 10.4+', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label">PHP <?php esc_html_e('Version', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['phpVer']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: PHP 8.1+', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label">PHP <?php esc_html_e('Memory Limit', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['phpMemLimit']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: 256M or higher', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label">PHP <?php esc_html_e('Max Input Vars', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['phpMaxInput']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: 1000 or higher', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label">PHP <?php esc_html_e('Max Post Size', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['phpMaxPost']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: 32M or higher', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Write Permissions', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value <?php echo $overview['uploadWritable'] ? 'wpok-env-ok' : 'wpok-env-fail'; ?>"><?php echo $overview['uploadWritable'] ? esc_html__('All Right', 'wp-optikit') : esc_html__('Not Writable', 'wp-optikit'); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Required for file operations', 'wp-optikit'); ?></small>
                </li>
            </ul>
        </section>

        <section class="wpok-card">
            <div class="wpok-card-heading">
                <div>
                    <h3><?php esc_html_e('PHP Extensions', 'wp-optikit'); ?></h3>
                </div>
            </div>
            <ul class="wpok-env-list">

                <?php foreach ($overview['extensions'] as $ext) : ?>
                    <li>
                        <span class="wpok-env-label"><?php echo esc_html($ext['label']); ?></span>
                        <span class="wpok-env-value <?php echo $ext['loaded'] ? 'wpok-env-ok' : 'wpok-env-fail'; ?>">
                            <?php echo $ext['loaded'] ? esc_html__('Yes', 'wp-optikit') : esc_html__('No', 'wp-optikit'); ?>
                        </span>
                        <small class="wpok-env-rec">
                            <?php echo esc_html($ext['note']); ?>
                            <?php if ($ext['required']) : ?>
                                <span class="wpok-ext-required"><?php esc_html_e('(required)', 'wp-optikit'); ?></span>
                            <?php endif; ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="wpok-card">
            <div class="wpok-card-heading">
                <div>
                    <h3><?php esc_html_e('WordPress Environment', 'wp-optikit'); ?></h3>
                </div>
            </div>
            <ul class="wpok-env-list">
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Version', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['wpVer']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: 6.7+', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Site URL', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['siteUrl']); ?></span>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Home URL', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['homeUrl']); ?></span>
                </li>
                <li>
                    <span class="wpok-env-label">WP <?php esc_html_e('Multisite', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo $overview['isMultisite'] ? esc_html__('Yes', 'wp-optikit') : esc_html__('No', 'wp-optikit'); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: No (for this setup)', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Max Upload Size', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['maxUploadSize']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: 32 MB or higher', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Memory Limit', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['wpMemLimit']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: 256M or higher', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Max Memory Limit', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['wpMaxMemLimit']); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: 256M or higher', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Permalink Structure', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo $overview['permalinkStr'] ? esc_html($overview['permalinkStr']) : esc_html__('Plain', 'wp-optikit'); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: /%postname%/', 'wp-optikit'); ?></small>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Language', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['locale']); ?></span>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Timezone', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['timezone']); ?></span>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Admin Email', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value"><?php echo esc_html($overview['adminEmail']); ?></span>
                </li>
                <li>
                    <span class="wpok-env-label"><?php esc_html_e('Debug Mode', 'wp-optikit'); ?></span>
                    <span class="wpok-env-value <?php echo $overview['wpDebug'] ? 'wpok-env-fail' : 'wpok-env-ok'; ?>"><?php echo $overview['wpDebug'] ? esc_html__('Active', 'wp-optikit') : esc_html__('Inactive', 'wp-optikit'); ?></span>
                    <small class="wpok-env-rec"><?php esc_html_e('Recommended: Inactive on production', 'wp-optikit'); ?></small>
                </li>
            </ul>
        </section>
    </div>

</section>
