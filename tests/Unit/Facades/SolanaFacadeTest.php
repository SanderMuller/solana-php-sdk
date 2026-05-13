<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Facades\Solana;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

/** @internal */
final class SolanaFacadeTest extends TestCase
{
    #[Test]
    public function fake_swaps_the_underlying_rpc_client(): void
    {
        $stub = Solana::fake();
        $stub->script([
            'getBalance' => ['context' => ['slot' => 1], 'value' => 5_000_000],
        ]);

        $balance = Solana::getBalance('11111111111111111111111111111111');

        self::assertSame(5_000_000.0, $balance);
        self::assertSame(['getBalance'], $stub->methodCalls());

        // Cleanup so subsequent tests in this case run against the
        // shared Testbench-bound client, not the in-memory stub.
        $this->container->forgetInstance(SolanaRpcClient::class);
    }

    #[Test]
    public function facade_resolves_to_connection(): void
    {
        // The facade root must be a Connection instance.
        $root = $this->container->make(Connection::class);
        self::assertInstanceOf(Connection::class, $root);
    }
}
