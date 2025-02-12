<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\SolanaRpcClient;
use Illuminate\Container\Container;

trait IsProgram
{
    private SolanaRpcClient $client {
        get {
            return Container::getInstance()->get(SolanaRpcClient::class);
        }
    }
}
