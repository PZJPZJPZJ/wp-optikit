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
                <div class="wpok-title-with-help">
                    <h3><?php esc_html_e('Coming Soon', 'wp-optikit'); ?></h3>
                    <?php
                    $helpText = __('This module is planned and does not provide available features yet.', 'wp-optikit');
                    include WPOK_DIR . 'templates/help-tooltip.php';
                    ?>
                </div>
            </div>
        </section>
    </div>
</section>
