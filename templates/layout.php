<?php
/**
 * @var array<string, mixed> $context
 */
$tabs       = $context['tabs'];
$activeTab  = $context['active_tab'];
?>
<div class="wrap wpok-admin-wrap">

    <div class="wpok-admin-tabs-bar">
        <nav class="wpok-admin-tabs wpok-admin-tabs-glass" aria-label="<?php echo esc_attr__( 'OptiKit sections', 'wp-optikit' ); ?>">
            <span class="wpok-admin-tab-logo">
                <span class="wpok-logo-text">OptiKit</span>
                <span class="wpok-version-badge">v<?php echo esc_html($context['plugin_version']); ?></span>
            </span>
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
    </div>

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
