<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Util;

use Mockery;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Programs\ComputeBudgetProgram;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Util\PriorityFee;

final class PriorityFeeTest extends TestCase
{
    #[Test]
    public function estimate_returns_nearest_rank_percentile(): void
    {
        $connection = $this->makeConnection([
            ['slot' => 1, 'prioritizationFee' => 10],
            ['slot' => 2, 'prioritizationFee' => 50],
            ['slot' => 3, 'prioritizationFee' => 100],
            ['slot' => 4, 'prioritizationFee' => 200],
            ['slot' => 5, 'prioritizationFee' => 1000],
        ]);

        // p75 of [10,50,100,200,1000] -> ceil(0.75*5)-1 = 3 -> 200
        self::assertSame(200, PriorityFee::estimate($connection, null, 0.75));
        // p100 -> 1000
        self::assertSame(1000, PriorityFee::estimate($connection, null, 1.0));
        // p20 -> ceil(1)-1 = 0 -> 10
        self::assertSame(10, PriorityFee::estimate($connection, null, 0.2));
    }

    #[Test]
    public function estimate_returns_zero_for_empty_sample(): void
    {
        $connection = $this->makeConnection([]);

        self::assertSame(0, PriorityFee::estimate($connection));
    }

    #[Test]
    public function estimate_rejects_out_of_range_percentile(): void
    {
        $this->expectException(InputValidationException::class);

        PriorityFee::estimate($this->makeConnection([]), null, 1.5);
    }

    #[Test]
    public function build_instructions_emits_limit_and_price(): void
    {
        $connection = $this->makeConnection([
            ['slot' => 1, 'prioritizationFee' => 500],
            ['slot' => 2, 'prioritizationFee' => 1500],
        ]);

        [$limit, $price] = PriorityFee::buildInstructions($connection, 200_000, null, 1.0);

        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $limit->programId->toBase58());
        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $price->programId->toBase58());
        // discriminator 2 + u32 LE 200_000 = 0x0D 0x0C 0x03 0x00 -> [0x40, 0x0D, 0x03, 0x00]
        self::assertSame(2, $limit->data->toArray()[0]);
        // discriminator 3 + u64 LE 1500 = 0xDC 0x05 0x00 ...
        self::assertSame(3, $price->data->toArray()[0]);
        self::assertSame(0xDC, $price->data->toArray()[1]);
    }

    /**
     * @param array<int, array{slot: int, prioritizationFee: int}> $samples
     */
    private function makeConnection(array $samples): Connection
    {
        $clientMock = Mockery::mock(SolanaRpcClient::class);
        /** @var Expectation $expectation */
        $expectation = $clientMock->allows('call');
        $expectation->with('getRecentPrioritizationFees', []);
        $expectation->andReturn($samples);

        $this->container->bind(SolanaRpcClient::class, static fn (): SolanaRpcClient => $clientMock);

        return $this->container->get(Connection::class);
    }
}
