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
            'formats'          => array('jpg', 'png', 'gif'),
            'quality'          => 80,
            'keep_original'    => false,
            'max_file_size_kb' => 512,
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

    public function keepOriginal(): bool
    {
        $settings = $this->get();

        return !empty($settings['keep_original']);
    }

    public function getMaxFileSizeBytes(): int
    {
        $settings = $this->get();

        return max(1, (int) $settings['max_file_size_kb']) * 1024;
    }

    public function shouldClearElementorCacheAfterJobs(): bool
    {
        $settings = $this->get();

        return !empty($settings['clear_elementor_cache_after_jobs']);
    }

    public function sanitize($input): array
    {
        $input   = is_array($input) ? $input : array();
        $formats = array_values(array_intersect((array) ($input['formats'] ?? array()), array('jpg', 'png', 'gif')));

        if (empty($formats)) {
            $formats = self::defaults()['formats'];
        }

        return array(
            'enabled'          => !empty($input['enabled']),
            'output_format'    => (($input['output_format'] ?? 'webp') === 'webp') ? 'webp' : 'webp',
            'formats'          => $formats,
            'quality'          => max(1, min(100, (int) ($input['quality'] ?? self::defaults()['quality']))),
            'keep_original'    => !empty($input['keep_original']),
            'max_file_size_kb' => max(1, min(1024 * 100, (int) ($input['max_file_size_kb'] ?? self::defaults()['max_file_size_kb']))),
            'clear_elementor_cache_after_jobs' => !empty($input['clear_elementor_cache_after_jobs']),
        );
    }
}
