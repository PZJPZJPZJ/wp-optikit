<?php
$environment = $context['environment'];
$modules     = $context['modules'];
$recentJobs  = $context['recent_jobs'];
$runningJobs = array_filter($recentJobs, static fn (array $job): bool => in_array($job['status'], array('pending', 'processing', 'cancelling'), true));
$completedJobs = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'succeeded');
$failedJobs = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'failed');
?>
<section class="wpok-overview-hero">
    <div class="wpok-overview-hero-main wpok-card">
        <span class="wpok-overview-kicker"><?php esc_html_e('Platform Status', 'wp-optikit'); ?></span>
        <h2><?php esc_html_e('Run optimization work from one place', 'wp-optikit'); ?></h2>
        <p><?php esc_html_e('Track queue activity, manage media operations, and prepare the next layer of performance tooling without jumping between separate plugins.', 'wp-optikit'); ?></p>
    </div>

    <div class="wpok-overview-metrics">
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label"><?php esc_html_e('Running jobs', 'wp-optikit'); ?></span>
            <strong><?php echo esc_html((string) count($runningJobs)); ?></strong>
            <small><?php esc_html_e('Active queue operations', 'wp-optikit'); ?></small>
        </section>
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label"><?php esc_html_e('Completed jobs', 'wp-optikit'); ?></span>
            <strong><?php echo esc_html((string) count($completedJobs)); ?></strong>
            <small><?php esc_html_e('Successful recent runs', 'wp-optikit'); ?></small>
        </section>
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label"><?php esc_html_e('Image module', 'wp-optikit'); ?></span>
            <strong><?php esc_html_e('Available', 'wp-optikit'); ?></strong>
            <small><?php esc_html_e('Optimization workspace is active', 'wp-optikit'); ?></small>
        </section>
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label"><?php esc_html_e('Environment', 'wp-optikit'); ?></span>
            <strong><?php echo esc_html($environment['php_current']); ?> / <?php echo esc_html($environment['wp_current']); ?></strong>
            <small><?php esc_html_e('PHP and WordPress runtime', 'wp-optikit'); ?></small>
        </section>
    </div>
</section>

<div class="wpok-grid wpok-grid-overview">
    <section class="wpok-card">
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker"><?php esc_html_e('Optimization Workspace', 'wp-optikit'); ?></span>
                <h2><?php esc_html_e('Modules', 'wp-optikit'); ?></h2>
            </div>
        </div>
        <div class="wpok-module-grid">
            <?php foreach ($modules as $module) : ?>
                <article class="wpok-module-card <?php echo $module['enabled'] ? 'is-enabled' : 'is-planned'; ?>">
                    <h3><?php echo esc_html($module['label']); ?></h3>
                    <p><?php echo $module['enabled']
                        ? esc_html__('Available now for day-to-day performance work.', 'wp-optikit')
                        : esc_html__('Coming soon as part of the broader OptiKit operations suite.', 'wp-optikit'); ?></p>
                    <span class="wpok-module-state"><?php echo $module['enabled']
                        ? esc_html__('Available', 'wp-optikit')
                        : esc_html__('Coming soon', 'wp-optikit'); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="wpok-card">
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker"><?php esc_html_e('Runtime Health', 'wp-optikit'); ?></span>
                <h2><?php esc_html_e('Environment', 'wp-optikit'); ?></h2>
            </div>
        </div>
        <ul class="wpok-stat-list">
            <li><span><?php esc_html_e('PHP runtime', 'wp-optikit'); ?></span><strong><?php echo esc_html($environment['php_current']); ?></strong><small><?php printf(__('Minimum supported: %s', 'wp-optikit'), esc_html($environment['php_min'])); ?></small></li>
            <li><span><?php esc_html_e('WordPress runtime', 'wp-optikit'); ?></span><strong><?php echo esc_html($environment['wp_current']); ?></strong><small><?php printf(__('Minimum supported: %s', 'wp-optikit'), esc_html($environment['wp_min'])); ?></small></li>
            <li><span><?php esc_html_e('Recent failures', 'wp-optikit'); ?></span><strong><?php echo esc_html((string) count($failedJobs)); ?></strong><small><?php esc_html_e('Jobs that ended with an error', 'wp-optikit'); ?></small></li>
        </ul>
    </section>
</div>
