<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\SolanaRpcClient;
use Illuminate\Container\Container;
use ReflectionProperty;

trait IsProgram
{
    private SolanaRpcClient $client {
        get {
            $reflectionProperty = new ReflectionProperty($this, 'client');

            if ($reflectionProperty->isInitialized($this)) {
                return $this->client;
            }

            return Container::getInstance()->get(SolanaRpcClient::class);
        }
    }
}
