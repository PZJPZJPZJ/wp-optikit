<?php
/**
 * @var array<string, mixed> $imageSettings
 */
$formats = (array) ($imageSettings['formats'] ?? array());
$outputFormat = (string) ($imageSettings['output_format'] ?? 'webp');
?>
<div class="wpok-grid wpok-grid-images">
    <section class="wpok-card">
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker"><?php esc_html_e('Configuration', 'wp-optikit'); ?></span>
                <h2><?php esc_html_e('Image Module Settings', 'wp-optikit'); ?></h2>
            </div>
        </div>
        <form method="post" action="options.php" class="wpok-settings-form">
            <?php settings_fields('wpok_image_settings_group'); ?>

            <div class="wpok-field-row">
                <label for="wpok-image-enabled"><?php esc_html_e('Enable image module', 'wp-optikit'); ?></label>
                <label class="wpok-inline-toggle">
                    <input id="wpok-image-enabled" type="checkbox" name="wpok_image_settings[enabled]" value="1" <?php checked(!empty($imageSettings['enabled'])); ?>>
                    <span><?php esc_html_e('Automatically convert supported uploads to the selected output format.', 'wp-optikit'); ?></span>
                </label>
            </div>

            <div class="wpok-field-row">
                <span><?php esc_html_e('Output format', 'wp-optikit'); ?></span>
                <div class="wpok-radio-group">
                    <label class="wpok-radio-card">
                        <input type="radio" name="wpok_image_settings[output_format]" value="webp" <?php checked($outputFormat, 'webp'); ?>>
                        <span><?php esc_html_e('WebP', 'wp-optikit'); ?></span>
                    </label>
                </div>
            </div>

            <div class="wpok-field-row">
                <span><?php esc_html_e('Source formats', 'wp-optikit'); ?></span>
                <div class="wpok-pill-group">
                    <?php foreach (array('jpg' => 'JPG', 'png' => 'PNG', 'gif' => 'GIF') as $value => $label) : ?>
                        <label class="wpok-pill">
                            <input type="checkbox" name="wpok_image_settings[formats][]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, $formats, true)); ?>>
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-quality"><?php esc_html_e('Target format quality', 'wp-optikit'); ?></label>
                <div class="wpok-range-wrap">
                    <input id="wpok-image-quality" type="range" min="1" max="100" name="wpok_image_settings[quality]" value="<?php echo esc_attr((string) ($imageSettings['quality'] ?? 80)); ?>" data-range-display>
                    <span class="wpok-range-value" data-role="quality-value"><?php echo esc_html((string) ($imageSettings['quality'] ?? 80)); ?></span>
                </div>
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-threshold"><?php esc_html_e('Oversized threshold (KB)', 'wp-optikit'); ?></label>
                <input id="wpok-image-threshold" type="number" min="1" max="102400" name="wpok_image_settings[max_file_size_kb]" value="<?php echo esc_attr((string) ($imageSettings['max_file_size_kb'] ?? 512)); ?>">
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-keep-original"><?php esc_html_e('Keep original file', 'wp-optikit'); ?></label>
                <label class="wpok-inline-toggle">
                    <input id="wpok-image-keep-original" type="checkbox" name="wpok_image_settings[keep_original]" value="1" <?php checked(!empty($imageSettings['keep_original'])); ?>>
                    <span><?php esc_html_e('Store the original upload path in attachment meta.', 'wp-optikit'); ?></span>
                </label>
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-elementor-cache"><?php esc_html_e('Clear Elementor cache after completed batch jobs', 'wp-optikit'); ?></label>
                <label class="wpok-inline-toggle">
                    <input id="wpok-image-elementor-cache" type="checkbox" name="wpok_image_settings[clear_elementor_cache_after_jobs]" value="1" <?php checked(!empty($imageSettings['clear_elementor_cache_after_jobs'])); ?>>
                    <span><?php esc_html_e('After image batch jobs finish, automatically trigger Elementor\'s cache clear routine when Elementor is active.', 'wp-optikit'); ?></span>
                </label>
            </div>

            <?php submit_button(__('Save Image Settings', 'wp-optikit'), 'primary', 'submit', false); ?>
        </form>
    </section>

    <section class="wpok-card wpok-image-app" data-wpok-image-app>
        <div class="wpok-card-heading">
            <div>
                <span class="wpok-card-kicker"><?php esc_html_e('Scan & Queue', 'wp-optikit'); ?></span>
                <h2><?php esc_html_e('Image Jobs', 'wp-optikit'); ?></h2>
            </div>
        </div>

        <div class="wpok-scan-mode-toggle" data-scan-mode-toggle>
            <button type="button" class="wpok-scan-mode-btn is-active" data-scan-mode="convert" data-scan-endpoint="images/scans/non-webp" data-job-type="image_convert">
                <?php esc_html_e('Batch Convert', 'wp-optikit'); ?>
            </button>
            <button type="button" class="wpok-scan-mode-btn" data-scan-mode="recompress" data-scan-endpoint="images/scans/oversized" data-job-type="image_recompress">
                <?php esc_html_e('Re-compress', 'wp-optikit'); ?>
            </button>
        </div>

        <div class="wpok-scan-panel" data-scan-panel>
            <div class="wpok-scan-panel-head" data-scan-panel-head>
                <div>
                    <h3 data-mode-title><?php esc_html_e('Batch Convert Existing Images', 'wp-optikit'); ?></h3>
                    <p data-mode-desc><?php esc_html_e('Scan the media library for attachments that have not yet been converted to the target format, then queue them for background processing.', 'wp-optikit'); ?></p>
                </div>
                <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Media Library', 'wp-optikit'); ?></button>
            </div>
            <div class="wpok-job-results" data-role="results"></div>
            <div class="wpok-job-toolbar" data-role="toolbar" hidden>
                <button type="button" class="button button-primary" data-action="start"><?php esc_html_e('Queue Conversion Job', 'wp-optikit'); ?></button>
            </div>
        </div>
    </section>

    <section class="wpok-card wpok-image-jobs-card" data-image-jobs>
        <div class="wpok-card-toolbar">
            <div>
                <span class="wpok-card-kicker"><?php esc_html_e('Image Operations', 'wp-optikit'); ?></span>
                <h2><?php esc_html_e('Images Workspace', 'wp-optikit'); ?></h2>
            </div>
            <div class="wpok-toolbar-actions">
                <button type="button" class="button button-secondary" data-action="clear-completed"><?php esc_html_e('Clear Completed Records', 'wp-optikit'); ?></button>
            </div>
        </div>
        <div data-role="image-jobs-content"></div>
    </section>
</div>
