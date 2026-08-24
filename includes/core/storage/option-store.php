<?php

namespace WPOptiKit\Core\Storage;

final class OptionStore
{
    public const CORE_OPTION     = 'wpok_core_settings';
    public const IMAGE_OPTION    = 'wpok_image_settings';
    public const CACHE_OPTION    = 'wpok_cache_settings';
    public const ASSETS_OPTION   = 'wpok_assets_settings';
    public const DATABASE_OPTION = 'wpok_database_settings';

    public function get(string $optionName, array $defaults = array()): array
    {
        $value = get_option($optionName, array());

        if (!is_array($value)) {
            $value = array();
        }

        return wp_parse_args($value, $defaults);
    }

    public function update(string $optionName, array $value): void
    {
        update_option($optionName, $value, false);
    }

    public function getCoreSettings(array $defaults = array()): array
    {
        return $this->get(self::CORE_OPTION, $defaults);
    }

    public function updateCoreSettings(array $value): void
    {
        $this->update(self::CORE_OPTION, $value);
    }

    public function hasLegacyOptions(): bool
    {
        $legacyKeys = array(
            'wpok_enabled',
            'wpok_convert_formats',
            'wpok_quality',
        );

        foreach ($legacyKeys as $key) {
            if (get_option($key, null) !== null) {
                return true;
            }
        }

        return false;
    }
}
