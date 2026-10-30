<?php
/**
 * @var array<string, mixed> $formsSettings
 */
?>
<section class="wpok-card">
    <div class="wpok-card-heading">
        <div>
            <span class="wpok-card-kicker"><?php esc_html_e('Configuration', 'wp-optikit'); ?></span>
            <h2><?php esc_html_e('Form Settings', 'wp-optikit'); ?></h2>
        </div>
    </div>
    <p><?php esc_html_e('These settings apply to all Elementor Pro forms. Elementor Pro must be active for validation to run.', 'wp-optikit'); ?></p>
    <form method="post" action="options.php" class="wpok-settings-form">
        <?php settings_fields('wpok_forms_settings_group'); ?>

        <div class="wpok-field-row">
            <label for="wpok-forms-referrer"><?php esc_html_e('Reject submissions with an empty referrer', 'wp-optikit'); ?></label>
            <label class="wpok-inline-toggle">
                <input type="hidden" name="wpok_forms_settings[reject_empty_referrer]" value="0">
                <input id="wpok-forms-referrer" type="checkbox" name="wpok_forms_settings[reject_empty_referrer]" value="1" <?php checked(!empty($formsSettings['reject_empty_referrer'])); ?>>
                <span><?php esc_html_e('Reject submissions when the submitted referrer field is missing, blank, or not a string.', 'wp-optikit'); ?></span>
            </label>
            <p><?php esc_html_e('Checks the submitted referrer field only. URLs, domains, and HTTP request headers are not validated. Disabled by default.', 'wp-optikit'); ?></p>
        </div>

        <?php submit_button(__('Save Form Settings', 'wp-optikit'), 'primary', 'submit', false); ?>
    </form>
</section>
