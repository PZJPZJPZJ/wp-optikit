<?php
$environment = $context['environment'];
$modules     = $context['modules'];
$recentJobs  = $context['recent_jobs'];
$runningJobs = array_filter($recentJobs, static fn (array $job): bool => in_array($job['status'], array('pending', 'processing', 'cancelling'), true));
$completedJobs = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'succeeded');
$failedJobs = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'failed');

$jobStatusLabels = array(
    'pending'    => __('Queued', 'wp-optikit'),
    'processing' => __('Running', 'wp-optikit'),
    'cancelling' => __('Cancelling', 'wp-optikit'),
    'succeeded'  => __('Completed', 'wp-optikit'),
    'failed'     => __('Failed', 'wp-optikit'),
    'cancelled'  => __('Cancelled', 'wp-optikit'),
    'skipped'    => __('Skipped', 'wp-optikit'),
);
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

<section class="wpok-card wpok-recent-jobs-card" data-recent-jobs data-limit="8">
    <div class="wpok-card-toolbar">
        <div>
            <span class="wpok-card-kicker"><?php esc_html_e('Queue Activity', 'wp-optikit'); ?></span>
            <h2><?php esc_html_e('Recent Jobs', 'wp-optikit'); ?></h2>
        </div>
        <div class="wpok-toolbar-actions">
            <button type="button" class="button button-secondary" data-action="clear-completed"><?php esc_html_e('Clear Completed Records', 'wp-optikit'); ?></button>
        </div>
    </div>

    <div data-role="recent-jobs-content">
        <?php if (empty($recentJobs)) : ?>
            <p class="description"><?php esc_html_e('No queue activity has been recorded yet.', 'wp-optikit'); ?></p>
        <?php else : ?>
            <table class="widefat striped wpok-job-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'wp-optikit'); ?></th>
                    <th><?php esc_html_e('Module', 'wp-optikit'); ?></th>
                    <th><?php esc_html_e('Type', 'wp-optikit'); ?></th>
                    <th><?php esc_html_e('Status', 'wp-optikit'); ?></th>
                    <th><?php esc_html_e('Progress', 'wp-optikit'); ?></th>
                    <th><?php esc_html_e('Updated', 'wp-optikit'); ?></th>
                    <th class="wpok-job-actions-col"><?php esc_html_e('Action', 'wp-optikit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentJobs as $job) : ?>
                    <?php
                    $percent = (int) (($job['total_items'] ?? 0) > 0 ? round(($job['processed_items'] / $job['total_items']) * 100) : 0);
                    ?>
                    <tr>
                        <td>#<?php echo esc_html((string) $job['id']); ?></td>
                        <td><?php echo esc_html($job['module']); ?></td>
                        <td><?php echo esc_html($job['job_type']); ?></td>
                        <td><span class="wpok-job-status is-<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($jobStatusLabels[$job['status']] ?? $job['status']); ?></span></td>
                        <td>
                            <div class="wpok-inline-progress">
                                <div class="wpok-progress-bar is-compact"><span style="width: <?php echo esc_attr((string) $percent); ?>%;"></span></div>
                                <span class="wpok-progress-count"><?php echo esc_html((string) $job['processed_items']); ?> / <?php echo esc_html((string) $job['total_items']); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($job['updated_at']); ?></td>
                        <td class="wpok-job-actions-cell">
                            <?php if (in_array($job['status'], array('pending', 'processing', 'cancelling'), true)) : ?>
                                <button type="button" class="button button-secondary" data-action="cancel-job" data-job-id="<?php echo esc_attr((string) $job['id']); ?>" <?php disabled($job['status'] === 'cancelling'); ?>>
                                    <?php echo $job['status'] === 'cancelling' ? __('Cancelling...', 'wp-optikit') : __('Cancel', 'wp-optikit'); ?>
                                </button>
                            <?php else : ?>
                                <span class="description"><?php esc_html_e('-', 'wp-optikit'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
