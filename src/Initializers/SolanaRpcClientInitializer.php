<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Initializers;

use Collectiq\SolanaPhpSdk\SolanaRpcClient;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final class SolanaRpcClientInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): SolanaRpcClient
    {
        return new SolanaRpcClient();
    }
}
