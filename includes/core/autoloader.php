<?php

namespace WPOptiKit\Core;

final class Autoloader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(
            static function (string $class) use ($baseDir): void {
                $prefix = 'WPOptiKit\\';

                if (strpos($class, $prefix) !== 0) {
                    return;
                }

                $relativeClass = substr($class, strlen($prefix));
                $segments      = explode('\\', $relativeClass);
                $relativePath  = implode(
                    DIRECTORY_SEPARATOR,
                    array_map(
                        static function (string $segment): string {
                            $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $segment);

                            return strtolower((string) $kebab);
                        },
                        $segments
                    )
                ) . '.php';
                $file          = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;

                if (is_readable($file)) {
                    require_once $file;
                }
            }
        );
    }
}
