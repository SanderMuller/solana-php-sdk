<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Fees;

use Illuminate\Http\Client\Request;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Fees\AutoComputeBudget;
use SanderMuller\SolanaPhpSdk\Fees\ComputeBudgetEstimator;
use SanderMuller\SolanaPhpSdk\Fees\FixedPriorityFee;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\ComputeBudgetProgram;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Transaction;

/** @internal */
final class AutoComputeBudgetTest extends TestCase
{
    #[Test]
    public function apply_prepends_compute_budget_instructions_using_simulated_units(): void
    {
        $client = $this->container->get(SolanaRpcClient::class);

        $client->fake(static function (Request $request): mixed {
            $body = $request->data();

            return SolanaRpcClient::response([
                'jsonrpc' => '2.0',
                'id' => $body['id'] ?? 'stub',
                'result' => [
                    'context' => ['slot' => 1],
                    'value' => [
                        'err' => null,
                        'logs' => [],
                        'unitsConsumed' => 20_000,
                        'accounts' => null,
                    ],
                ],
            ]);
        });

        $connection = new Connection();
        $payer = Keypair::generate();
        $to = Keypair::generate()->getPublicKey();

        $tx = new Transaction(recentBlockhash: '11111111111111111111111111111111', feePayer: $payer->getPublicKey());
        $tx->addInstructions(SystemProgram::transfer(
            fromPubkey: $payer->getPublicKey(),
            toPublicKey: $to,
            lamports: 1,
        ));

        AutoComputeBudget::apply(
            $connection,
            $tx,
            [$payer],
            new FixedPriorityFee(123),
            new ComputeBudgetEstimator(bufferRatio: 1.5),
        );

        // First two instructions are now ComputeBudget setComputeUnitLimit + setComputeUnitPrice
        self::assertCount(3, $tx->instructions);
        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $tx->instructions[0]->programId->toBase58());
        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $tx->instructions[1]->programId->toBase58());
    }

    #[Test]
    public function apply_preserves_caller_request_heap_frame(): void
    {
        $client = $this->container->get(SolanaRpcClient::class);
        $client->fake(static fn (Request $r): mixed => SolanaRpcClient::response([
            'jsonrpc' => '2.0',
            'id' => $r->data()['id'] ?? 'stub',
            'result' => ['context' => ['slot' => 1], 'value' => ['unitsConsumed' => 5_000, 'err' => null, 'logs' => []]],
        ]));

        $connection = new Connection();
        $payer = Keypair::generate();
        $to = Keypair::generate()->getPublicKey();

        $tx = new Transaction(recentBlockhash: '11111111111111111111111111111111', feePayer: $payer->getPublicKey());
        $tx->addInstructions(
            ComputeBudgetProgram::requestHeapFrame(64 * 1024),
            SystemProgram::transfer($payer->getPublicKey(), $to, 1),
        );

        AutoComputeBudget::apply($connection, $tx, [$payer], new FixedPriorityFee(1));

        // Heap-frame survives; auto pair is prepended: [limit, price, heapFrame, transfer].
        self::assertCount(4, $tx->instructions);
        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $tx->instructions[0]->programId->toBase58());
        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $tx->instructions[1]->programId->toBase58());
        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $tx->instructions[2]->programId->toBase58());
    }

    #[Test]
    public function apply_refuses_to_overwrite_caller_compute_unit_limit_by_default(): void
    {
        $client = $this->container->get(SolanaRpcClient::class);
        $client->fake(static fn (Request $r): mixed => SolanaRpcClient::response([
            'jsonrpc' => '2.0',
            'id' => $r->data()['id'] ?? 'stub',
            'result' => ['context' => ['slot' => 1], 'value' => ['unitsConsumed' => 5_000, 'err' => null, 'logs' => []]],
        ]));

        $connection = new Connection();
        $payer = Keypair::generate();
        $to = Keypair::generate()->getPublicKey();

        $tx = new Transaction(recentBlockhash: '11111111111111111111111111111111', feePayer: $payer->getPublicKey());
        $tx->addInstructions(
            ComputeBudgetProgram::setComputeUnitLimit(200_000),
            SystemProgram::transfer($payer->getPublicKey(), $to, 1),
        );

        $this->expectException(InvalidArgumentException::class);

        AutoComputeBudget::apply($connection, $tx, [$payer], new FixedPriorityFee(1), replaceExisting: false);
    }

    #[Test]
    public function apply_is_idempotent(): void
    {
        $client = $this->container->get(SolanaRpcClient::class);
        $client->fake(static fn (Request $r): mixed => SolanaRpcClient::response([
            'jsonrpc' => '2.0',
            'id' => $r->data()['id'] ?? 'stub',
            'result' => ['context' => ['slot' => 1], 'value' => ['unitsConsumed' => 5_000, 'err' => null, 'logs' => []]],
        ]));

        $connection = new Connection();
        $payer = Keypair::generate();
        $to = Keypair::generate()->getPublicKey();

        $tx = new Transaction(recentBlockhash: '11111111111111111111111111111111', feePayer: $payer->getPublicKey());
        $tx->addInstructions(SystemProgram::transfer($payer->getPublicKey(), $to, 1));

        AutoComputeBudget::apply($connection, $tx, [$payer], new FixedPriorityFee(1));
        AutoComputeBudget::apply($connection, $tx, [$payer], new FixedPriorityFee(2));

        // Still exactly 3 instructions (2 compute-budget + transfer), not 5.
        self::assertCount(3, $tx->instructions);
    }
}
