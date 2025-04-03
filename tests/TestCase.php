<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests;

use Collectiq\SolanaPhpSdk\Bootstrap;
use Collectiq\SolanaPhpSdk\Services\SolanaRpcClient;
use DG\BypassFinals;
use Illuminate\Container\Container;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public Container $container;

    public mixed $config;

    protected function setUp(): void
    {
        BypassFinals::enable(bypassReadOnly: false);

        $this->container = Bootstrap::createContainer(__DIR__ . '/../config/solana-php-sdk.php');

        $jsonFilePath = dirname(__DIR__) . '/src/Programs/SNS/Constants/config.json';
        $this->config = json_decode(file_get_contents($jsonFilePath), true);
    }

    public function assembleClient(string $rpc_method, array $rpc_params): SolanaRpcClient
    {
        return $this->container->get(SolanaRpcClient::class);
    }
}
