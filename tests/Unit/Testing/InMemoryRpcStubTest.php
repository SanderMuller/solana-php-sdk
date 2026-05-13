<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;
use SanderMuller\SolanaPhpSdk\Testing\InMemoryRpcStub;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class InMemoryRpcStubTest extends TestCase
{
    #[Test]
    public function records_method_calls_in_order(): void
    {
        $stub = InMemoryRpcStub::create()->script([
            'getBalance' => ['value' => 5_000_000],
            'getLatestBlockhash' => [
                'context' => ['slot' => 1],
                'value' => ['blockhash' => '11111111111111111111111111111111', 'lastValidBlockHeight' => 250],
            ],
        ]);

        $this->container->bind(SolanaRpcClient::class, static fn (): SolanaRpcClient => $stub->client());
        $connection = $this->container->get(Connection::class);

        $connection->getBalance('11111111111111111111111111111111');
        $connection->latestBlockhash();
        $connection->getBalance('11111111111111111111111111111111');

        self::assertSame(['getBalance', 'getLatestBlockhash', 'getBalance'], $stub->methodCalls());
    }

    #[Test]
    public function callable_handler_can_advance_through_a_sequence(): void
    {
        $calls = 0;
        $stub = InMemoryRpcStub::create()->script([
            'getBalance' => static function () use (&$calls): array {
                $calls++;

                return ['value' => $calls * 1_000];
            },
        ]);

        $this->container->bind(SolanaRpcClient::class, static fn (): SolanaRpcClient => $stub->client());
        $connection = $this->container->get(Connection::class);

        $first = $connection->getBalance('11111111111111111111111111111111');
        $second = $connection->getBalance('11111111111111111111111111111111');

        self::assertSame(1_000.0, $first);
        self::assertSame(2_000.0, $second);
        self::assertSame(2, $calls);
    }

    #[Test]
    public function calling_script_twice_swaps_the_active_script(): void
    {
        // Earlier draft installed each `script()` call as an additional
        // `Factory::fake()` callback, so the first registration always
        // won. Guard the rewrite contract: the second call wins.
        $stub = InMemoryRpcStub::create()->script(['getBalance' => ['value' => 1]]);

        $this->container->bind(SolanaRpcClient::class, static fn (): SolanaRpcClient => $stub->client());
        $connection = $this->container->get(Connection::class);

        self::assertSame(1.0, $connection->getBalance('11111111111111111111111111111111'));

        $stub->script(['getBalance' => ['value' => 2]]);
        self::assertSame(2.0, $connection->getBalance('11111111111111111111111111111111'));
    }

    #[Test]
    public function reset_history_clears_recorded_calls_but_keeps_script(): void
    {
        $stub = InMemoryRpcStub::create()->script([
            'getBalance' => ['value' => 1],
        ]);

        $this->container->bind(SolanaRpcClient::class, static fn (): SolanaRpcClient => $stub->client());
        $connection = $this->container->get(Connection::class);

        $connection->getBalance('11111111111111111111111111111111');
        self::assertCount(1, $stub->methodCalls());

        $stub->resetHistory();
        self::assertSame([], $stub->methodCalls());

        $connection->getBalance('11111111111111111111111111111111');
        self::assertSame(['getBalance'], $stub->methodCalls());
    }
}
