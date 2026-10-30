<?php

namespace WPOptiKit\Modules\Forms;

final class ElementorFormValidator
{
    public function __construct(private readonly FormsSettings $settings)
    {
    }

    public function register(): void
    {
        add_action('elementor_pro/forms/validation', array($this, 'validate'), 10, 2);
    }

    public function validate(object $record, object $ajaxHandler): void
    {
        if (!$this->settings->shouldRejectEmptyReferrer()) {
            return;
        }

        $referrer = $_POST['referrer'] ?? '';

        if (is_string($referrer) && trim(wp_unslash($referrer)) !== '') {
            return;
        }

        $message = __('Submission rejected.', 'wp-optikit');

        // Elementor checks field errors before running submission actions.
        // Use an independent key so this does not depend on a form field ID.
        $ajaxHandler->add_error('wpok_referrer', $message);
        $ajaxHandler->add_error_message($message);
    }
}
