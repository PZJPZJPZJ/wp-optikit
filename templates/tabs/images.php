<?php
/**
 * @var array<string, mixed> $imageSettings
 */
$formats = (array) ($imageSettings['formats'] ?? array());
$outputFormat = (string) ($imageSettings['output_format'] ?? 'webp');
?>
<div class="wpok-grid wpok-grid-images">
    <section class="wpok-card">
        <h2>Image Settings</h2>
        <form method="post" action="options.php" class="wpok-settings-form">
            <?php settings_fields('wpok_image_settings_group'); ?>

            <div class="wpok-field-row">
                <label for="wpok-image-enabled">Enable image module</label>
                <label class="wpok-inline-toggle">
                    <input id="wpok-image-enabled" type="checkbox" name="wpok_image_settings[enabled]" value="1" <?php checked(!empty($imageSettings['enabled'])); ?>>
                    <span>Automatically convert supported uploads to the target format.</span>
                </label>
            </div>

            <div class="wpok-field-row">
                <span>Output format</span>
                <div class="wpok-radio-group">
                    <label class="wpok-radio-card">
                        <input type="radio" name="wpok_image_settings[output_format]" value="webp" <?php checked($outputFormat, 'webp'); ?>>
                        <span>WebP</span>
                    </label>
                </div>
            </div>

            <div class="wpok-field-row">
                <span>Source formats</span>
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
                <label for="wpok-image-quality">Target format quality</label>
                <input id="wpok-image-quality" type="number" min="1" max="100" name="wpok_image_settings[quality]" value="<?php echo esc_attr((string) ($imageSettings['quality'] ?? 80)); ?>">
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-threshold">Oversized threshold (KB)</label>
                <input id="wpok-image-threshold" type="number" min="1" max="102400" name="wpok_image_settings[max_file_size_kb]" value="<?php echo esc_attr((string) ($imageSettings['max_file_size_kb'] ?? 512)); ?>">
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-keep-original">Keep original file</label>
                <label class="wpok-inline-toggle">
                    <input id="wpok-image-keep-original" type="checkbox" name="wpok_image_settings[keep_original]" value="1" <?php checked(!empty($imageSettings['keep_original'])); ?>>
                    <span>Store the original upload path in attachment meta.</span>
                </label>
            </div>

            <div class="wpok-field-row">
                <label for="wpok-image-elementor-cache">Clear Elementor cache after completed batch jobs</label>
                <label class="wpok-inline-toggle">
                    <input id="wpok-image-elementor-cache" type="checkbox" name="wpok_image_settings[clear_elementor_cache_after_jobs]" value="1" <?php checked(!empty($imageSettings['clear_elementor_cache_after_jobs'])); ?>>
                    <span>After image batch jobs finish, automatically trigger Elementor's cache clear routine when Elementor is active.</span>
                </label>
            </div>

            <?php submit_button('Save Image Settings', 'primary', 'submit', false); ?>
        </form>
    </section>

    <section class="wpok-card wpok-image-app" data-wpok-image-app>
        <h2>Image Jobs</h2>
        <p class="description">Scans use REST endpoints and batch work is executed by the background queue.</p>

        <div class="wpok-job-panels">
            <div class="wpok-job-panel" data-job-panel="convert" data-job-type="image_convert" data-scan-endpoint="images/scans/non-webp">
                <div class="wpok-job-panel-head">
                    <div>
                        <h3>Batch Convert Existing Images</h3>
                        <p>Find attachments that have not been converted to WebP yet.</p>
                    </div>
                    <button type="button" class="button button-secondary" data-action="scan">Scan Media Library</button>
                </div>
                <div class="wpok-job-results" data-role="results"></div>
                <div class="wpok-job-toolbar" data-role="toolbar" hidden>
                    <button type="button" class="button button-primary" data-action="start">Queue Conversion Job</button>
                </div>
                <div class="wpok-job-progress" data-role="progress" hidden>
                    <div class="wpok-job-progress-meta">
                        <strong data-role="job-status">pending</strong>
                        <span data-role="job-count">0 / 0</span>
                    </div>
                    <div class="wpok-progress-bar"><span data-role="job-bar"></span></div>
                    <div class="wpok-job-progress-actions">
                        <span class="description" data-role="job-message">Waiting for job creation.</span>
                        <button type="button" class="button-link-delete" data-action="cancel">Cancel job</button>
                    </div>
                </div>
            </div>

            <div class="wpok-job-panel" data-job-panel="recompress" data-job-type="image_recompress" data-scan-endpoint="images/scans/oversized">
                <div class="wpok-job-panel-head">
                    <div>
                        <h3>Re-compress Oversized Images</h3>
                        <p>Find attachments above the configured size threshold and re-run compression in the queue.</p>
                    </div>
                    <button type="button" class="button button-secondary" data-action="scan">Scan Oversized Images</button>
                </div>
                <div class="wpok-job-results" data-role="results"></div>
                <div class="wpok-job-toolbar" data-role="toolbar" hidden>
                    <button type="button" class="button button-primary" data-action="start">Queue Re-compression Job</button>
                </div>
                <div class="wpok-job-progress" data-role="progress" hidden>
                    <div class="wpok-job-progress-meta">
                        <strong data-role="job-status">pending</strong>
                        <span data-role="job-count">0 / 0</span>
                    </div>
                    <div class="wpok-progress-bar"><span data-role="job-bar"></span></div>
                    <div class="wpok-job-progress-actions">
                        <span class="description" data-role="job-message">Waiting for job creation.</span>
                        <button type="button" class="button-link-delete" data-action="cancel">Cancel job</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
