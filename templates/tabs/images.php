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
<section class="wpok-section" aria-labelledby="wpok-image-settings-title">
    <header class="wpok-section-heading">
        <h2 id="wpok-image-settings-title"><?php esc_html_e('Settings', 'wp-optikit'); ?></h2>
        <p><?php esc_html_e('Configure image conversion and Elementor integration.', 'wp-optikit'); ?></p>
    </header>
    <form method="post" action="options.php" class="wpok-settings-form" aria-labelledby="wpok-image-settings-title">
        <input type="hidden" name="option_page" value="wpok_image_settings_group">
        <input type="hidden" name="action" value="update">
        <?php wp_nonce_field('wpok_image_settings_group-options', '_wpnonce', false); ?>
        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(admin_url('admin.php?page=wp-optikit&tab=images')); ?>">
        <div class="wpok-grid">
            <section class="wpok-card" aria-labelledby="wpok-conversion-title">
                <div class="wpok-card-heading"><h3 id="wpok-conversion-title"><?php esc_html_e('Conversion Settings', 'wp-optikit'); ?></h3></div>
                <div class="wpok-fields">
                    <div class="wpok-field-row">
                        <div class="wpok-field-label">
                            <label class="wpok-inline-toggle" for="wpok-image-enabled">
                                <input id="wpok-image-enabled" aria-describedby="wpok-image-enabled-description" type="checkbox" name="wpok_image_settings[enabled]" value="1" <?php checked(!empty($imageSettings['enabled'])); ?>>
                                <span><?php esc_html_e('Enable image module', 'wp-optikit'); ?></span>
                            </label>
                            <?php
                            $helpId = 'wpok-image-enabled-description';
                            $helpParagraphs = [
                                __('Automatically convert supported uploads to the selected output format.', 'wp-optikit'),
                            ];
                            include WPOK_DIR . 'templates/help-tooltip.php';
                            ?>
                        </div>
                    </div>

                    <fieldset class="wpok-field-row">
                        <legend><?php esc_html_e('Output format', 'wp-optikit'); ?></legend>
                        <div class="wpok-radio-group">
                            <label class="wpok-radio-card">
                                <input type="radio" name="wpok_image_settings[output_format]" value="webp" <?php checked($outputFormat, 'webp'); ?>>
                                <span><?php esc_html_e('WebP', 'wp-optikit'); ?></span>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="wpok-field-row">
                        <legend><?php esc_html_e('Source formats', 'wp-optikit'); ?></legend>
                        <div class="wpok-pill-group">
                            <?php foreach (array('jpg' => 'JPG', 'png' => 'PNG', 'gif' => 'GIF') as $value => $label) : ?>
                                <label class="wpok-pill">
                                    <input type="checkbox" name="wpok_image_settings[formats][]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, $formats, true)); ?>>
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="wpok-field-row" aria-describedby="wpok-image-engine-description">
                        <legend>
                            <span class="wpok-field-label">
                                <span><?php esc_html_e('Conversion engine', 'wp-optikit'); ?></span>
                                <?php
                                $helpId = 'wpok-image-engine-description';
                                $helpParagraphs = [
                                    __('Imagick offers better quality and preserves PNG transparency. GD is a fallback when Imagick is unavailable but loses PNG alpha channel (transparency becomes black). Animated GIF to WebP conversion requires Imagick with animated WebP support (libwebp-anim); GIFs are skipped automatically when the engine does not support animated conversion.', 'wp-optikit'),
                                ];
                                include WPOK_DIR . 'templates/help-tooltip.php';
                                ?>
                            </span>
                        </legend>
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
                    </fieldset>

                    <div class="wpok-field-row">
                        <div class="wpok-field-label">
                            <label for="wpok-image-quality"><?php esc_html_e('Target format quality', 'wp-optikit'); ?></label>
                            <?php
                            $helpId = 'wpok-image-quality-description';
                            $helpParagraphs = [
                                __('Default: 80. Recommended range: 75–90. Higher values produce better quality but larger file sizes.', 'wp-optikit'),
                            ];
                            include WPOK_DIR . 'templates/help-tooltip.php';
                            ?>
                        </div>
                        <div class="wpok-range-wrap">
                            <input id="wpok-image-quality" aria-describedby="wpok-image-quality-description" type="range" min="1" max="100" name="wpok_image_settings[quality]" value="<?php echo esc_attr((string) ($imageSettings['quality'] ?? 80)); ?>" data-range-display>
                            <span class="wpok-range-value" data-role="quality-value"><?php echo esc_html((string) ($imageSettings['quality'] ?? 80)); ?></span>
                        </div>
                    </div>

                </div>
            </section>
            <section class="wpok-card" aria-labelledby="wpok-integration-title">
                <div class="wpok-card-heading"><h3 id="wpok-integration-title"><?php esc_html_e('Elementor Integration', 'wp-optikit'); ?></h3></div>
                <div class="wpok-fields">
                    <div class="wpok-field-row">
                        <div class="wpok-field-label">
                            <label class="wpok-inline-toggle" for="wpok-image-elementor-cache">
                                <input id="wpok-image-elementor-cache" aria-describedby="wpok-image-elementor-cache-description" type="checkbox" name="wpok_image_settings[clear_elementor_cache_after_jobs]" value="1" <?php checked(!empty($imageSettings['clear_elementor_cache_after_jobs'])); ?>>
                                <span><?php esc_html_e('Clear Elementor cache after completed batch jobs', 'wp-optikit'); ?></span>
                            </label>
                            <?php
                            $helpId = 'wpok-image-elementor-cache-description';
                            $helpParagraphs = [
                                __('After image batch jobs finish, automatically trigger Elementor\'s cache clear routine when Elementor is active.', 'wp-optikit'),
                            ];
                            include WPOK_DIR . 'templates/help-tooltip.php';
                            ?>
                        </div>
                    </div>

                </div>
            </section>
        </div>
        <div class="wpok-form-actions"><?php submit_button(__('Save Image Settings', 'wp-optikit'), 'primary', 'submit', false); ?></div>
    </form>
</section>
<section class="wpok-section" aria-labelledby="wpok-image-tools-title">
    <header class="wpok-section-heading">
        <h2 id="wpok-image-tools-title"><?php esc_html_e('Tools', 'wp-optikit'); ?></h2>
        <p><?php esc_html_e('Scan images and files, then run the appropriate conversion or cleanup operation.', 'wp-optikit'); ?></p>
    </header>
    <div class="wpok-grid">
        <section class="wpok-card" data-job-panel="convert" data-job-type="image_convert" data-scan-endpoint="images/scans/non-webp">
            <div class="wpok-job-panel-head">
                <div>
                    <h3><?php esc_html_e('Batch Convert Existing Images', 'wp-optikit'); ?></h3>
                    <p class="description"><?php esc_html_e('Scan the media library for attachments that have not yet been converted to the target format, then queue them for background processing.', 'wp-optikit'); ?></p>
                </div>
                <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Media Library', 'wp-optikit'); ?></button>
            </div>
            <div class="wpok-job-results" data-role="results"></div>
            <div class="wpok-job-toolbar" data-role="toolbar" hidden>
                <button type="button" class="button button-primary" data-action="start"><?php esc_html_e('Queue Conversion Job', 'wp-optikit'); ?></button>
            </div>
        </section>
        <section class="wpok-card" data-orphan-panel data-orphan-endpoint="media-orphan">
            <div class="wpok-job-panel-head">
                <div>
                    <h3><?php esc_html_e('Orphan Files', 'wp-optikit'); ?></h3>
                    <p class="description"><?php esc_html_e('Find files in the uploads directory that have no corresponding attachment record in the database.', 'wp-optikit'); ?></p>
                </div>
                <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Orphan Files', 'wp-optikit'); ?></button>
            </div>
            <div class="wpok-job-results" data-role="results"></div>
            <div class="wpok-job-toolbar" data-role="toolbar" hidden></div>
        </section>
        <section class="wpok-card" data-missing-panel data-missing-endpoint="media-missing">
            <div class="wpok-job-panel-head">
                <div>
                    <h3><?php esc_html_e('Missing Files', 'wp-optikit'); ?></h3>
                    <p class="description"><?php esc_html_e('Find missing attachment files and clean only the affected records or metadata.', 'wp-optikit'); ?></p>
                </div>
                <button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Missing Files', 'wp-optikit'); ?></button>
            </div>
            <div class="wpok-job-results" data-role="results"></div>
            <div class="wpok-job-toolbar" data-role="toolbar" hidden></div>
        </section>
    </div>

</section>
<section class="wpok-section" aria-labelledby="wpok-image-tasks-title">
    <header class="wpok-section-heading">
        <h2 id="wpok-image-tasks-title"><?php esc_html_e('Task Records', 'wp-optikit'); ?></h2>
        <p><?php esc_html_e('View image task progress, cancel active tasks, and clear completed records.', 'wp-optikit'); ?></p>
    </header>
    <div class="wpok-grid">
        <section class="wpok-card wpok-image-jobs-card" data-image-jobs>
            <div class="wpok-card-toolbar">
                <div>
                    <h3 id="wpok-image-jobs-title"><?php esc_html_e('Image Tasks', 'wp-optikit'); ?></h3>
                </div>
                <div class="wpok-toolbar-actions">
                    <button type="button" class="button button-secondary" data-action="clear-completed"><?php esc_html_e('Clear Completed Records', 'wp-optikit'); ?></button>
                </div>
            </div>
            <div class="wpok-table-scroll" data-role="image-jobs-content" tabindex="0" role="region" aria-labelledby="wpok-image-jobs-title"></div>
        </section>
    </div>
</section>
