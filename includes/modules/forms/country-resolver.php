<?php

namespace WPOptiKit\Modules\Forms;

final class CountryResolver
{
    /** @return array<string, array{name: string, dial: string}> */
    public static function countries(): array
    {
        // Keep the source names independent of locale for stable sorting.
        static $countries = null;

        if ($countries === null) {
            $countries = require __DIR__ . '/countries.php';
            uasort($countries, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        }

        return $countries;
    }

    public static function normalize(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $code = strtoupper(trim($value));

        return preg_match('/^[A-Z]{2}$/D', $code) === 1 && isset(self::countries()[$code]) ? $code : '';
    }

    public static function display(string $code): string
    {
        $code = self::normalize($code);

        if ($code === '') {
            return '';
        }

        $country = self::countries()[$code];

        // Country names are declared in the translation catalog alongside the dataset.
        return __($country['name'], 'wp-optikit') . ' (' . ($country['dial'] !== '' ? $country['dial'] : __('N/A', 'wp-optikit')) . ')';
    }

    /** @return array<string, string> */
    public static function selectOptions(): array
    {
        $options = array();

        foreach (self::countries() as $code => $country) {
            $options[$code] = self::display($code);
        }

        return $options;
    }

    public static function submittedValue(array $field): mixed
    {
        return array_key_exists('raw_value', $field) ? $field['raw_value'] : ($field['value'] ?? '');
    }
}
