<?php

namespace WPOptiKit\Modules\Forms;

use WPOptiKit\Core\Admin\AdminPageRegistry;
use WPOptiKit\Core\Container;
use WPOptiKit\Core\Contracts\ModuleInterface;

final class FormsModule implements ModuleInterface
{
    private FormsSettings $settings;
    private ElementorFormValidator $validator;
    private ElementorCountryCode $countryCode;

    public function __construct(Container $container)
    {
        $this->settings    = new FormsSettings($container->get('options'));
        $this->validator   = new ElementorFormValidator($this->settings);
        $this->countryCode = new ElementorCountryCode($this->settings);
    }

    public function get_id(): string
    {
        return 'forms';
    }

    public function get_label(): string
    {
        return __('Forms', 'wp-optikit');
    }

    public function is_enabled(): bool
    {
        return $this->settings->shouldRejectEmptyReferrer() || $this->settings->isCountryCodeEnabled();
    }

    public function register(): void
    {
        $this->settings->register();
        $this->validator->register();
        $this->countryCode->register();
    }

    public function register_admin(AdminPageRegistry $admin): void
    {
        $settings = $this->settings;

        $admin->addTab(
            'forms',
            $this->get_label(),
            static function (array $context) use ($settings): void {
                $formsSettings = $settings->get();
                $elementorFormsAvailable = class_exists('ElementorPro\\Modules\\Forms\\Fields\\Field_Base');

                include WPOK_DIR . 'templates/tabs/forms.php';
            }
        );
    }
}
