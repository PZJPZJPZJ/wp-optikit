<?php

namespace WPOptiKit\Core;

final class Compatibility
{
    public const MIN_PHP = '8.1';
    public const MIN_WP  = '6.7';

    public static function meetsRequirements(): bool
    {
        global $wp_version;

        return version_compare(PHP_VERSION, self::MIN_PHP, '>=')
            && version_compare((string) $wp_version, self::MIN_WP, '>=');
    }

    public static function registerRuntimeNotice(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action(
            'admin_notices',
            static function (): void {
                $message = sprintf(
                    __('WP OptiKit requires PHP %1$s+ and WordPress %2$s+. Current environment: PHP %3$s, WordPress %4$s.', 'wp-optikit'),
                    self::MIN_PHP,
                    self::MIN_WP,
                    PHP_VERSION,
                    get_bloginfo('version')
                );

                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            }
        );
    }

    public static function abortActivation(): void
    {
        deactivate_plugins(plugin_basename(WPOK_FILE));
        unset($_GET['activate']);

        $message = sprintf(
            'WP OptiKit requires PHP %1$s+ and WordPress %2$s+. Current environment: PHP %3$s, WordPress %4$s.',
            self::MIN_PHP,
            self::MIN_WP,
            PHP_VERSION,
            get_bloginfo('version')
        );

        wp_die(esc_html($message), __('WP OptiKit Activation Error', 'wp-optikit'), array('back_link' => true));
    }
}
