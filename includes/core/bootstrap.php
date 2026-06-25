<?php

namespace WPOptiKit\Core;

final class Bootstrap
{
    private static ?Plugin $plugin = null;

    public static function boot(string $pluginFile): void
    {
        self::loadCoreFiles();

        if (!Compatibility::meetsRequirements()) {
            Compatibility::registerRuntimeNotice();
            return;
        }

        self::plugin($pluginFile)->boot();
    }

    public static function activate(): void
    {
        self::loadCoreFiles();

        if (!Compatibility::meetsRequirements()) {
            Compatibility::abortActivation();
        }

        self::plugin(WPOK_FILE)->activate();
    }

    private static function plugin(string $pluginFile): Plugin
    {
        if (self::$plugin === null) {
            self::$plugin = new Plugin($pluginFile);
        }

        return self::$plugin;
    }

    private static function loadCoreFiles(): void
    {
        require_once WPOK_DIR . 'includes/core/autoloader.php';
        Autoloader::register(WPOK_DIR . 'includes');
        require_once WPOK_DIR . 'includes/core/compatibility.php';
    }
}
