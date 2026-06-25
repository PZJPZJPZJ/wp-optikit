<?php

namespace WPOptiKit\Modules\Image;

final class ElementorCacheBridge
{
    public function clear(): bool
    {
        if (!did_action('elementor/loaded') || !class_exists('\Elementor\Plugin')) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance ?? null;

        if (!is_object($plugin)) {
            return false;
        }

        if (isset($plugin->files_manager) && is_object($plugin->files_manager) && method_exists($plugin->files_manager, 'clear_cache')) {
            $plugin->files_manager->clear_cache();
            return true;
        }

        return false;
    }
}
