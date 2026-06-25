<?php
$environment = $context['environment'];
$modules     = $context['modules'];
$recentJobs  = $context['recent_jobs'];
$runningJobs = array_filter($recentJobs, static fn (array $job): bool => in_array($job['status'], array('pending', 'processing', 'cancelling'), true));
$completedJobs = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'succeeded');
$failedJobs = array_filter($recentJobs, static fn (array $job): bool => $job['status'] === 'failed');

$jobStatusLabels = array(
    'pending'    => 'Queued',
    'processing' => 'Running',
    'cancelling' => 'Cancelling',
    'succeeded'  => 'Completed',
    'failed'     => 'Failed',
    'cancelled'  => 'Cancelled',
    'skipped'    => 'Skipped',
);
?>
<section class="wpok-overview-hero">
    <div class="wpok-overview-hero-main wpok-card">
        <span class="wpok-overview-kicker">Platform Status</span>
        <h2>Run optimization work from one place</h2>
        <p>Track queue activity, manage media operations, and prepare the next layer of performance tooling without jumping between separate plugins.</p>
    </div>

    <div class="wpok-overview-metrics">
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label">Running jobs</span>
            <strong><?php echo esc_html((string) count($runningJobs)); ?></strong>
            <small>Active queue operations</small>
        </section>
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label">Completed jobs</span>
            <strong><?php echo esc_html((string) count($completedJobs)); ?></strong>
            <small>Successful recent runs</small>
        </section>
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label">Image module</span>
            <strong>Available</strong>
            <small>Optimization workspace is active</small>
        </section>
        <section class="wpok-card wpok-metric-card">
            <span class="wpok-metric-label">Environment</span>
            <strong><?php echo esc_html($environment['php_current']); ?> / <?php echo esc_html($environment['wp_current']); ?></strong>
            <small>PHP and WordPress runtime</small>
        </section>
    </div>
</section>

<div class="wpok-grid wpok-grid-overview">
    <section class="wpok-card">
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker">Optimization Workspace</span>
                <h2>Modules</h2>
            </div>
        </div>
        <div class="wpok-module-grid">
            <?php foreach ($modules as $module) : ?>
                <article class="wpok-module-card <?php echo $module['enabled'] ? 'is-enabled' : 'is-planned'; ?>">
                    <h3><?php echo esc_html($module['label']); ?></h3>
                    <p><?php echo $module['enabled'] ? 'Available now for day-to-day performance work.' : 'Coming soon as part of the broader OptiKit operations suite.'; ?></p>
                    <span class="wpok-module-state"><?php echo $module['enabled'] ? 'Available' : 'Coming soon'; ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="wpok-card">
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker">Runtime Health</span>
                <h2>Environment</h2>
            </div>
        </div>
        <ul class="wpok-stat-list">
            <li><span>PHP runtime</span><strong><?php echo esc_html($environment['php_current']); ?></strong><small>Minimum supported: <?php echo esc_html($environment['php_min']); ?></small></li>
            <li><span>WordPress runtime</span><strong><?php echo esc_html($environment['wp_current']); ?></strong><small>Minimum supported: <?php echo esc_html($environment['wp_min']); ?></small></li>
            <li><span>Recent failures</span><strong><?php echo esc_html((string) count($failedJobs)); ?></strong><small>Jobs that ended with an error</small></li>
        </ul>
    </section>
</div>

<section class="wpok-card wpok-recent-jobs-card" data-recent-jobs data-limit="8">
    <div class="wpok-card-toolbar">
        <div>
            <span class="wpok-card-kicker">Queue Activity</span>
            <h2>Recent Jobs</h2>
        </div>
        <div class="wpok-toolbar-actions">
            <button type="button" class="button button-secondary" data-action="clear-completed">Clear Completed Records</button>
        </div>
    </div>

    <div data-role="recent-jobs-content">
        <?php if (empty($recentJobs)) : ?>
            <p class="description">No queue activity has been recorded yet.</p>
        <?php else : ?>
            <table class="widefat striped wpok-job-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Module</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Updated</th>
                    <th class="wpok-job-actions-col">Action</th>
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
                                    <?php echo $job['status'] === 'cancelling' ? 'Cancelling...' : 'Cancel'; ?>
                                </button>
                            <?php else : ?>
                                <span class="description">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
