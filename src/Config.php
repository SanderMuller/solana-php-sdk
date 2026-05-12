<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use InvalidArgumentException;

final class Config
{
    /** @var array<string, mixed> */
    private static array $config;

    public static function load(string $path): void
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Config file not found: {$path}");
        }

        $loaded = require $path;
        if (! is_array($loaded)) {
            throw new InvalidArgumentException("Config file {$path} must return an array.");
        }

        /** @var array<string, mixed> $loaded */
        self::$config = $loaded;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }
}
