/**
 * Elementor editor template for the OptiKit Country Code field.
 */
(function () {
    'use strict';

    if (!window.elementor || !window.elementor.hooks || !window.wpokCountryCodeEditor) {
        return;
    }

    var countries = window.wpokCountryCodeEditor.countries;

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
        });
    }

    window.elementor.hooks.addFilter('elementor_pro/forms/content_template/field/wpok_country_code', function (input, item, index) {
        var code = Object.prototype.hasOwnProperty.call(countries, item.wpok_default_country) ? item.wpok_default_country : 'US';
        var id = item.custom_id || 'form_field_' + index;
        var required = item.required ? ' required aria-required="true"' : '';
        var classes = 'elementor-field-textual elementor-field ' + (item.css_classes || '');
        var html = '<div class="elementor-field elementor-select-wrapper remove-before">';
        html += '<div class="select-caret-down-wrapper"><svg aria-hidden="true" class="e-font-icon-svg e-eicon-caret-down" viewBox="0 0 571.4 571.4"><path d="M571 393Q571 407 561 418L311 668Q300 679 286 679T261 668L11 418Q0 407 0 393T11 368 36 357H536Q550 357 561 368T571 393Z"></path></svg></div>';
        html += '<select id="form-field-' + escapeHtml(id) + '" name="form_fields[' + escapeHtml(id) + ']" class="' + escapeHtml(classes) + '" aria-label="' + escapeHtml(item.field_label) + '"' + required + '>';

        Object.keys(countries).forEach(function (country) {
            html += '<option value="' + escapeHtml(country) + '"' + (country === code ? ' selected' : '') + '>' + escapeHtml(countries[country]) + '</option>';
        });

        return html + '</select></div>';
    }, 10, 3);
})();
