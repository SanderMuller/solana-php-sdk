<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Initializers\SolanaRpcClientInitializer;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;

final class Bootstrap
{
    public static function createContainer(string $configPath): Container
    {
        // Load configuration
        Config::load($configPath);

        // Set up the container
        $container = new GenericContainer();
        $container->addInitializer(SolanaRpcClientInitializer::class);

        return $container;
    }
}

Bootstrap::createContainer(__DIR__ . '/../config/solana-php-sdk.php');
