<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use InvalidArgumentException;

final class Config
{
    private static array $config;

    public static function load(string $path): void
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Config file not found: {$path}");
        }

        self::$config = require $path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }
}
