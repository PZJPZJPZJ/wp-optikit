<?php
/**
 * @var array<string, mixed> $imageSettings
 * @var string $engine
 * @var bool $gdAvailable
 * @var bool $imagickAvailable
 */
$formats = (array) ($imageSettings['formats'] ?? array());
$outputFormat = (string) ($imageSettings['output_format'] ?? 'webp');
?>
<div class="wpok-grid wpok-grid-images">
    <!-- 1. Configuration -->
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
                <span><?php esc_html_e('Conversion engine', 'wp-optikit'); ?></span>
                <div class="wpok-radio-group">
                    <label class="wpok-radio-card<?php echo !$imagickAvailable ? ' is-disabled' : ''; ?>">
                        <input type="radio" name="wpok_image_settings[engine]" value="imagick" <?php checked($engine, 'imagick'); ?> <?php disabled(!$imagickAvailable); ?>>
                        <span>Imagick</span>
                        <?php if (!$imagickAvailable) : ?>
                            <span class="wpok-engine-note"><?php esc_html_e('(not available)', 'wp-optikit'); ?></span>
                        <?php endif; ?>
                    </label>
                    <label class="wpok-radio-card<?php echo !$gdAvailable ? ' is-disabled' : ''; ?>">
                        <input type="radio" name="wpok_image_settings[engine]" value="gd" <?php checked($engine, 'gd'); ?> <?php disabled(!$gdAvailable); ?>>
                        <span>GD</span>
                        <?php if (!$gdAvailable) : ?>
                            <span class="wpok-engine-note"><?php esc_html_e('(not available)', 'wp-optikit'); ?></span>
                        <?php endif; ?>
                    </label>
                </div>
                <p class="wpok-engine-description"><?php esc_html_e('Imagick offers better quality and preserves PNG transparency. GD is a fallback when Imagick is unavailable but loses PNG alpha channel (transparency becomes black). Animated GIF to WebP conversion requires Imagick with animated WebP support (libwebp-anim); GIFs are skipped automatically when the engine does not support animated conversion.', 'wp-optikit'); ?></p>
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-quality"><?php esc_html_e('Target format quality', 'wp-optikit'); ?></label>
                <div class="wpok-range-wrap">
                    <input id="wpok-image-quality" type="range" min="1" max="100" name="wpok_image_settings[quality]" value="<?php echo esc_attr((string) ($imageSettings['quality'] ?? 80)); ?>" data-range-display>
                    <span class="wpok-range-value" data-role="quality-value"><?php echo esc_html((string) ($imageSettings['quality'] ?? 80)); ?></span>
                </div>
                <p class="wpok-engine-description"><?php esc_html_e('Default: 80. Recommended range: 75–90. Higher values produce better quality but larger file sizes.', 'wp-optikit'); ?></p>
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-threshold"><?php esc_html_e('Oversized threshold (KB)', 'wp-optikit'); ?></label>
                <input id="wpok-image-threshold" type="number" min="1" max="102400" name="wpok_image_settings[max_file_size_kb]" value="<?php echo esc_attr((string) ($imageSettings['max_file_size_kb'] ?? 512)); ?>">
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

    <!-- 2. Images Workspace (was #3, moved to #2) -->
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
<div class="wpok-grid wpok-grid-images-bottom">
    <!-- 3. Batch Convert -->
    <section class="wpok-card" data-job-panel="convert" data-job-type="image_convert" data-scan-endpoint="images/scans/non-webp">
        <div class="wpok-job-panel-head">
            <div>
                <h3><?php esc_html_e('Batch Convert Existing Images', 'wp-optikit'); ?></h3>
                <p><?php esc_html_e('Scan the media library for attachments that have not yet been converted to the target format, then queue them for background processing.', 'wp-optikit'); ?></p>
            </div>
            <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Media Library', 'wp-optikit'); ?></button>
        </div>
        <div class="wpok-job-results" data-role="results"></div>
        <div class="wpok-job-toolbar" data-role="toolbar" hidden>
            <button type="button" class="button button-primary" data-action="start"><?php esc_html_e('Queue Conversion Job', 'wp-optikit'); ?></button>
        </div>
    </section>

    <!-- 4. Re-compress -->
    <section class="wpok-card" data-job-panel="recompress" data-job-type="image_recompress" data-scan-endpoint="images/scans/oversized">
        <div class="wpok-job-panel-head">
            <div>
                <h3><?php esc_html_e('Re-compress Oversized Images', 'wp-optikit'); ?></h3>
                <p><?php esc_html_e('Detect oversized attachments and re-run compression in the background using the current output settings.', 'wp-optikit'); ?></p>
            </div>
            <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Oversized Images', 'wp-optikit'); ?></button>
        </div>
        <div class="wpok-job-results" data-role="results"></div>
        <div class="wpok-job-toolbar" data-role="toolbar" hidden>
            <button type="button" class="button button-primary" data-action="start"><?php esc_html_e('Queue Re-compression Job', 'wp-optikit'); ?></button>
        </div>
    </section>

    <!-- 5. Orphan Files -->
    <section class="wpok-card" data-orphan-panel data-orphan-endpoint="media-orphan">
        <div class="wpok-job-panel-head">
            <div>
                <h3><?php esc_html_e('Orphan Files', 'wp-optikit'); ?></h3>
                <p><?php esc_html_e('Find files in the uploads directory that have no corresponding attachment record in the database.', 'wp-optikit'); ?></p>
            </div>
            <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Orphan Files', 'wp-optikit'); ?></button>
        </div>
        <div class="wpok-job-results" data-role="results"></div>
        <div class="wpok-job-toolbar" data-role="toolbar" hidden></div>
    </section>

    <!-- 6. Missing Files -->
    <section class="wpok-card" data-missing-panel data-missing-endpoint="media-missing">
        <div class="wpok-job-panel-head">
            <div>
                <h3><?php esc_html_e('Missing Files', 'wp-optikit'); ?></h3>
                <p><?php esc_html_e('Find attachment records whose files are missing from the disk.', 'wp-optikit'); ?></p>
            </div>
            <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Missing Files', 'wp-optikit'); ?></button>
        </div>
        <div class="wpok-job-results" data-role="results"></div>
        <div class="wpok-job-toolbar" data-role="toolbar" hidden></div>
    </section>
</div>
