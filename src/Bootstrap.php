<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Programs\SnsProgram;
use Illuminate\Container\Container;

final class Bootstrap
{
    public static function createContainer(string $configPath): Container
    {
        // Load configuration
        Config::load($configPath);

        $container = Container::getInstance();
        $container->singleton(SolanaRpcClient::class, fn (): SolanaRpcClient => new SolanaRpcClient());
        $container->bind(SnsProgram::class, fn (): SnsProgram => new SnsProgram());

        return $container;
    }
}
