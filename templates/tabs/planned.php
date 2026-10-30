<?php
/**
 * @var string $plannedTitle
 * @var string $plannedDescription
 */
?>
<section class="wpok-section">
    <header class="wpok-section-heading">
        <h2><?php echo esc_html($plannedTitle); ?></h2>
        <p><?php echo esc_html($plannedDescription); ?></p>
    </header>
    <div class="wpok-grid">
        <section class="wpok-card">
            <div class="wpok-card-heading">
                <h3><?php esc_html_e('Coming Soon', 'wp-optikit'); ?></h3>
            </div>
            <p class="description"><?php esc_html_e('This module is planned and does not provide available features yet.', 'wp-optikit'); ?></p>
        </section>
    </div>
</section>
