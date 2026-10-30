<?php

namespace WPOptiKit\Modules\Forms;

use WPOptiKit\Core\Storage\OptionStore;

final class FormsSettings
{
    public function __construct(private readonly OptionStore $options)
    {
    }

    public static function defaults(): array
    {
        return array(
            'reject_empty_referrer' => false,
            'country_code_enabled'  => false,
        );
    }

    public function register(): void
    {
        add_action('admin_init', array($this, 'registerSetting'));
    }

    public function registerSetting(): void
    {
        register_setting(
            'wpok_forms_settings_group',
            OptionStore::FORMS_OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize'),
                'default'           => self::defaults(),
            )
        );
    }

    public function get(): array
    {
        return $this->options->get(OptionStore::FORMS_OPTION, self::defaults());
    }

    public function shouldRejectEmptyReferrer(): bool
    {
        return $this->get()['reject_empty_referrer'] === true;
    }

    public function isCountryCodeEnabled(): bool
    {
        return $this->get()['country_code_enabled'] === true;
    }

    public function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : array();

        return array(
            'reject_empty_referrer' => in_array($input['reject_empty_referrer'] ?? false, array(true, 1, '1'), true),
            'country_code_enabled'  => in_array($input['country_code_enabled'] ?? false, array(true, 1, '1'), true),
        );
    }
}
