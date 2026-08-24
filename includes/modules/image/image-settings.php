<?php

namespace WPOptiKit\Modules\Image;

use WPOptiKit\Core\Storage\OptionStore;

final class ImageSettings
{
    public function __construct(private readonly OptionStore $options)
    {
    }

    public static function defaults(): array
    {
        return array(
            'enabled'          => true,
            'output_format'    => 'webp',
            'formats'          => array('jpg', 'png'),
            'engine'           => 'imagick',
            'quality'          => 80,
            'clear_elementor_cache_after_jobs' => false,
        );
    }

    public function register(): void
    {
        add_action('admin_init', array($this, 'registerSetting'));
    }

    public function registerSetting(): void
    {
        register_setting(
            'wpok_image_settings_group',
            OptionStore::IMAGE_OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize'),
                'default'           => self::defaults(),
            )
        );
    }

    public function get(): array
    {
        return $this->options->get(OptionStore::IMAGE_OPTION, self::defaults());
    }

    public function isEnabled(): bool
    {
        $settings = $this->get();

        return !empty($settings['enabled']);
    }

    public function getFormats(): array
    {
        $settings = $this->get();

        return array_values(array_intersect((array) $settings['formats'], array('jpg', 'png', 'gif')));
    }

    public function getOutputFormat(): string
    {
        $settings = $this->get();

        return ($settings['output_format'] ?? 'webp') === 'webp' ? 'webp' : 'webp';
    }

    public function getQuality(): int
    {
        $settings = $this->get();

        return max(1, min(100, (int) $settings['quality']));
    }

    public function shouldClearElementorCacheAfterJobs(): bool
    {
        $settings = $this->get();

        return !empty($settings['clear_elementor_cache_after_jobs']);
    }

    public function getEngine(): string
    {
        $settings = $this->get();
        $engine   = (string) ($settings['engine'] ?? 'imagick');

        if (!in_array($engine, array('gd', 'imagick'), true)) {
            $engine = 'imagick';
        }

        if (!$this->isEngineAvailable($engine)) {
            $engine = $this->isEngineAvailable('gd') ? 'gd' : 'imagick';
        }

        return $engine;
    }

    public function isEngineAvailable(string $engine): bool
    {
        return match ($engine) {
            'gd'      => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
            default   => false,
        };
    }

    public function getAvailableEngines(): array
    {
        $engines = array();

        if (extension_loaded('gd')) {
            $engines['gd'] = __('GD', 'wp-optikit');
        }

        if (extension_loaded('imagick')) {
            $engines['imagick'] = __('Imagick', 'wp-optikit');
        }

        return $engines;
    }

    public function sanitize($input): array
    {
        $input   = is_array($input) ? $input : array();
        $formats = array_values(array_intersect((array) ($input['formats'] ?? array()), array('jpg', 'png', 'gif')));

        if (empty($formats)) {
            $formats = self::defaults()['formats'];
        }

        $engine = (string) ($input['engine'] ?? 'imagick');

        if (!in_array($engine, array('gd', 'imagick'), true)) {
            $engine = 'imagick';
        }

        if (!$this->isEngineAvailable($engine)) {
            $engine = $this->isEngineAvailable('gd') ? 'gd' : 'imagick';
        }

        return array(
            'enabled'          => !empty($input['enabled']),
            'output_format'    => (($input['output_format'] ?? 'webp') === 'webp') ? 'webp' : 'webp',
            'formats'          => $formats,
            'engine'           => $engine,
            'quality'          => max(1, min(100, (int) ($input['quality'] ?? self::defaults()['quality']))),
            'clear_elementor_cache_after_jobs' => !empty($input['clear_elementor_cache_after_jobs']),
        );
    }
}
