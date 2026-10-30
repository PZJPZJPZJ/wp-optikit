<?php
/**
 * @var array<string, mixed> $formsSettings
 * @var bool $elementorFormsAvailable
 */
?>
<section class="wpok-section" aria-labelledby="wpok-elementor-form-title">
    <header class="wpok-section-heading">
        <h2 id="wpok-elementor-form-title"><?php esc_html_e('Elementor Form', 'wp-optikit'); ?></h2>
        <p><?php esc_html_e('Manage security and additional features for Elementor Pro forms.', 'wp-optikit'); ?></p>
        <?php if (!$elementorFormsAvailable) : ?>
            <p class="wpok-forms-dependency" role="status"><?php esc_html_e('Elementor Pro is required for these features to run. You can save settings before activating it.', 'wp-optikit'); ?></p>
        <?php endif; ?>
    </header>

    <form method="post" action="options.php" class="wpok-settings-form" aria-labelledby="wpok-elementor-form-title">
        <input type="hidden" name="option_page" value="wpok_forms_settings_group">
        <input type="hidden" name="action" value="update">
        <?php wp_nonce_field('wpok_forms_settings_group-options', '_wpnonce', false); ?>
        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(admin_url('admin.php?page=wp-optikit&tab=forms')); ?>">

        <div class="wpok-grid">
            <section class="wpok-card" aria-labelledby="wpok-forms-security-title">
                <div class="wpok-card-heading">
                    <h3 id="wpok-forms-security-title"><?php esc_html_e('Security', 'wp-optikit'); ?></h3>
                </div>
                <div class="wpok-fields">
                    <div class="wpok-field-row">
                        <label class="wpok-inline-toggle" for="wpok-forms-referrer">
                            <input type="hidden" name="wpok_forms_settings[reject_empty_referrer]" value="0">
                            <input id="wpok-forms-referrer" type="checkbox" name="wpok_forms_settings[reject_empty_referrer]" value="1" aria-describedby="wpok-forms-referrer-description" <?php checked(!empty($formsSettings['reject_empty_referrer'])); ?>>
                            <span><?php esc_html_e('Reject submissions with an empty referrer', 'wp-optikit'); ?></span>
                        </label>
                        <div id="wpok-forms-referrer-description" class="wpok-field-description">
                            <p><?php esc_html_e('Reject submissions when the submitted referrer field is missing, blank, or not a string.', 'wp-optikit'); ?></p>
                            <p><?php esc_html_e('Checks the submitted referrer field only. URLs, domains, and HTTP request headers are not validated. Disabled by default.', 'wp-optikit'); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="wpok-card" aria-labelledby="wpok-forms-features-title">
                <div class="wpok-card-heading">
                    <h3 id="wpok-forms-features-title"><?php esc_html_e('Features', 'wp-optikit'); ?></h3>
                </div>
                <div class="wpok-fields">
                    <div class="wpok-field-row">
                        <label class="wpok-inline-toggle" for="wpok-forms-country-code">
                            <input type="hidden" name="wpok_forms_settings[country_code_enabled]" value="0">
                            <input id="wpok-forms-country-code" type="checkbox" name="wpok_forms_settings[country_code_enabled]" value="1" aria-describedby="wpok-forms-country-code-description" <?php checked(!empty($formsSettings['country_code_enabled'])); ?>>
                            <span><?php esc_html_e('Enable Country Code field', 'wp-optikit'); ?></span>
                        </label>
                        <div id="wpok-forms-country-code-description" class="wpok-field-description">
                            <p><?php esc_html_e('Adds a country/region and dialing code dropdown to Elementor Pro forms. Disabled by default.', 'wp-optikit'); ?></p>
                            <p><?php esc_html_e('After saving, choose Country Code as the field type in the Elementor Form widget. Set the default country and Cloudflare detection for each field there.', 'wp-optikit'); ?></p>
                            <p><?php esc_html_e('Detection falls back to the default country when unavailable. Keep this feature enabled while your forms use this field.', 'wp-optikit'); ?></p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="wpok-form-actions">
            <?php submit_button(__('Save Form Settings', 'wp-optikit'), 'primary', 'submit', false); ?>
        </div>
    </form>
</section>
