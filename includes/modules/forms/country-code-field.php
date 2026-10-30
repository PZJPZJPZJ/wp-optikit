<?php

namespace WPOptiKit\Modules\Forms;

final class CountryCodeField extends \ElementorPro\Modules\Forms\Fields\Field_Base
{
    // Older Elementor releases use this property instead of get_script_depends().
    public $depended_scripts = array('wpok-country-code');

    public function get_type(): string
    {
        return 'wpok_country_code';
    }

    public function get_name(): string
    {
        return __('Country Code', 'wp-optikit');
    }

    public function get_script_depends(): array
    {
        return array('wpok-country-code');
    }

    public function update_controls($widget): void
    {
        $control = \Elementor\Plugin::instance()->controls_manager->get_control_from_stack($widget->get_unique_name(), 'form_fields');

        if (is_wp_error($control) || empty($control['fields']) || !is_array($control['fields'])) {
            return;
        }

        $common = array(
            'condition'    => array('field_type' => $this->get_type()),
            'tab'          => 'content',
            'inner_tab'    => 'form_fields_content_tab',
            'tabs_wrapper' => 'form_fields_tabs',
            'render_type'  => 'template',
        );
        $controls = array(
            'wpok_use_cf_ipcountry' => array_merge($common, array(
                'name'         => 'wpok_use_cf_ipcountry',
                'label'        => __('Detect country with Cloudflare', 'wp-optikit'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'label_on'     => __('Yes', 'wp-optikit'),
                'label_off'    => __('No', 'wp-optikit'),
                'description'  => __('Uses this site\'s /cdn-cgi/trace endpoint. If detection is unavailable, the default country is used.', 'wp-optikit'),
            )),
            'wpok_default_country' => array_merge($common, array(
                'name'    => 'wpok_default_country',
                'label'   => __('Default Country', 'wp-optikit'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'US',
                'options' => CountryResolver::selectOptions(),
            )),
        );

        $control['fields'] = $this->inject_field_controls($control['fields'], $controls);
        $widget->update_control('form_fields', $control);
    }

    public function render($item, $item_index, $form): void
    {
        $defaultCountry = CountryResolver::normalize($item['wpok_default_country'] ?? 'US') ?: 'US';
        $key = 'input' . $item_index;

        // Reuse Elementor's name, ID, required state and custom CSS classes.
        $form->add_render_attribute($key, array(
            'class'                  => 'elementor-field-textual elementor-size-' . sanitize_html_class((string) $form->get_settings_for_display('input_size')),
            'data-wpok-country-code' => '1',
            'data-use-cf-ipcountry'   => ($item['wpok_use_cf_ipcountry'] ?? 'yes') === 'yes' ? '1' : '0',
            'data-default-country'   => $defaultCountry,
        ));

        if (!empty($item['field_label'])) {
            $form->add_render_attribute($key, 'aria-label', $item['field_label']);
        }

        echo '<div class="elementor-field elementor-select-wrapper remove-before">';
        echo '<div class="select-caret-down-wrapper"><svg aria-hidden="true" class="e-font-icon-svg e-eicon-caret-down" viewBox="0 0 571.4 571.4" xmlns="http://www.w3.org/2000/svg"><path d="M571 393Q571 407 561 418L311 668Q300 679 286 679T261 668L11 418Q0 407 0 393T11 368 36 357H536Q550 357 561 368T571 393Z"></path></svg></div>';
        echo '<select ' . $form->get_render_attribute_string($key) . '>';

        foreach (CountryResolver::selectOptions() as $code => $label) {
            printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr($code), selected($defaultCountry, $code, false), esc_html($label));
        }

        echo '</select></div>';
    }

    public function validation($field, $record, $ajax_handler): void
    {
        $value = CountryResolver::submittedValue($field);
        $id = (string) ($field['id'] ?? '');
        $submitted = $_POST['form_fields'] ?? array();

        // Elementor may flatten arrays before field validation; reject the original shape too.
        $invalidShape = is_array($submitted) && array_key_exists($id, $submitted) && !is_string($submitted[$id]);

        if ($invalidShape || ($value !== '' && CountryResolver::normalize($value) === '')) {
            $ajax_handler->add_error($id, __('Please select a valid country/region.', 'wp-optikit'));
        }
    }
}
