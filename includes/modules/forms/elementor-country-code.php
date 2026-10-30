<?php

namespace WPOptiKit\Modules\Forms;

final class ElementorCountryCode
{
    public function __construct(private readonly FormsSettings $settings)
    {
    }

    public function register(): void
    {
        add_action('elementor_pro/forms/fields/register', array($this, 'registerField'));
        add_action('wp_enqueue_scripts', array($this, 'registerScripts'));
        add_action('elementor/editor/after_enqueue_scripts', array($this, 'enqueueEditorScript'));
        add_action('elementor_pro/forms/process/wpok_country_code', array($this, 'processField'), 10, 3);
    }

    public function registerField(object $registrar): void
    {
        if (!$this->settings->isCountryCodeEnabled() || !class_exists('ElementorPro\\Modules\\Forms\\Fields\\Field_Base')) {
            return;
        }

        $this->registerScripts();
        $registrar->register(new CountryCodeField());
    }

    public function registerScripts(): void
    {
        if (!$this->settings->isCountryCodeEnabled()) {
            return;
        }

        wp_register_script('wpok-country-code', WPOK_URL . 'assets/js/country-code.js', array(), WPOK_VERSION, true);
    }

    public function enqueueEditorScript(): void
    {
        if (!$this->settings->isCountryCodeEnabled() || !class_exists('ElementorPro\\Modules\\Forms\\Fields\\Field_Base')) {
            return;
        }

        wp_enqueue_script('wpok-country-code-editor', WPOK_URL . 'assets/js/country-code-editor.js', array('elementor-editor'), WPOK_VERSION, true);
        wp_localize_script('wpok-country-code-editor', 'wpokCountryCodeEditor', array('countries' => CountryResolver::selectOptions()));
    }

    public function processField(array $field, object $record, object $ajaxHandler): void
    {
        if (!$this->settings->isCountryCodeEnabled()) {
            return;
        }

        $value = CountryResolver::submittedValue($field);

        if ($value === '') {
            return;
        }

        $code = CountryResolver::normalize($value);
        $id = (string) ($field['id'] ?? '');

        if ($code === '') {
            $ajaxHandler->add_error($id, __('Please select a valid country/region.', 'wp-optikit'));
            return;
        }

        try {
            $record->update_field($id, 'raw_value', $code);
            $record->update_field($id, 'value', CountryResolver::display($code));
        } catch (\Throwable $error) {
            error_log('WP OptiKit country field processing failed: ' . $error->getMessage());
            $ajaxHandler->add_error($id, __('The country field could not be processed. Please try again.', 'wp-optikit'));
        }
    }
}
