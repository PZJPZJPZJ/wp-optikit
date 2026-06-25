<?php
/**
 * @var array<string, mixed> $context
 */
$tabs       = $context['tabs'];
$activeTab  = $context['active_tab'];
?>
<div class="wrap wpok-admin-wrap">
    <div class="wpok-admin-hero">
        <div class="wpok-admin-hero-copy">
            <span class="wpok-admin-kicker"><?php echo esc_html__( 'Performance Operations Platform', 'wp-optikit' ); ?></span>
            <h1>OptiKit</h1>
            <p><?php echo esc_html__( 'Run media optimization, queue orchestration, cache workflows, and maintenance operations from one focused WordPress workspace.', 'wp-optikit' ); ?></p>
        </div>
        <div class="wpok-admin-hero-side">
            <span class="wpok-admin-badge">v<?php echo esc_html($context['plugin_version']); ?></span>
            <span class="wpok-admin-hero-meta"><?php echo esc_html__( 'WordPress performance workspace', 'wp-optikit' ); ?></span>
        </div>
    </div>

    <nav class="wpok-admin-tabs" aria-label="<?php echo esc_attr__( 'OptiKit sections', 'wp-optikit' ); ?>">
        <?php foreach ($tabs as $tabItem) : ?>
            <?php $isActive = $tabItem['id'] === $activeTab['id']; ?>
            <button
                type="button"
                class="wpok-admin-tab<?php echo $isActive ? ' is-active' : ''; ?>"
                data-tab-trigger="<?php echo esc_attr($tabItem['id']); ?>"
                aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
            >
                <?php echo esc_html($tabItem['label']); ?>
            </button>
        <?php endforeach; ?>
    </nav>

    <div class="wpok-admin-content">
        <?php foreach ($tabs as $tabItem) : ?>
            <section
                class="wpok-tab-panel<?php echo $tabItem['id'] === $activeTab['id'] ? ' is-active' : ''; ?>"
                data-tab-panel="<?php echo esc_attr($tabItem['id']); ?>"
                <?php echo $tabItem['id'] === $activeTab['id'] ? '' : 'hidden'; ?>
            >
                <?php call_user_func($tabItem['renderer'], $context); ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
