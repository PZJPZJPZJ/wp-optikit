<?php
$environment = $context['environment'];
$modules     = $context['modules'];
$recentJobs  = $context['recent_jobs'];
?>
<div class="wpok-grid wpok-grid-overview">
    <section class="wpok-card">
        <h2>Environment</h2>
        <ul class="wpok-stat-list">
            <li><span>PHP</span><strong><?php echo esc_html($environment['php_current']); ?></strong><small>minimum <?php echo esc_html($environment['php_min']); ?></small></li>
            <li><span>WordPress</span><strong><?php echo esc_html($environment['wp_current']); ?></strong><small>minimum <?php echo esc_html($environment['wp_min']); ?></small></li>
        </ul>
    </section>

    <section class="wpok-card">
        <h2>Modules</h2>
        <div class="wpok-module-grid">
            <?php foreach ($modules as $module) : ?>
                <article class="wpok-module-card <?php echo $module['enabled'] ? 'is-enabled' : 'is-planned'; ?>">
                    <h3><?php echo esc_html($module['label']); ?></h3>
                    <p><?php echo $module['enabled'] ? 'Operational in this release.' : 'Registered as a planned module placeholder.'; ?></p>
                    <span class="wpok-module-state"><?php echo $module['enabled'] ? 'Enabled' : 'Planned'; ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="wpok-card wpok-recent-jobs-card" data-recent-jobs data-limit="8">
    <div class="wpok-card-toolbar">
        <h2>Recent Jobs</h2>
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
                        <td><span class="wpok-job-status is-<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($job['status']); ?></span></td>
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
