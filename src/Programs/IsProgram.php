<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\SolanaRpcClient;
use Tempest\Container\Inject;

trait IsProgram
{
    #[Inject]
    private readonly SolanaRpcClient $client;
}
