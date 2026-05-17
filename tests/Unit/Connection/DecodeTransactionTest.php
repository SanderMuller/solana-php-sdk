<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\DecodedTransactionSource;

/** @internal */
final class DecodeTransactionTest extends TestCase
{
    #[Test]
    public function decode_transaction_routes_through_get_transaction_rpc_and_returns_decoded_tree(): void
    {
        $this->fakeRpcByMethod([
            'getTransaction' => [
                'slot' => 42,
                'blockTime' => 1_700_000_000,
                'transaction' => [
                    'signatures' => ['sig-xyz'],
                    'message' => [
                        'header' => [
                            'numRequiredSignatures' => 1,
                            'numReadonlySignedAccounts' => 0,
                            'numReadonlyUnsignedAccounts' => 1,
                        ],
                        'accountKeys' => ['Payer', 'Program'],
                        'instructions' => [
                            ['programIdIndex' => 1, 'accounts' => [0], 'data' => ''],
                        ],
                        'recentBlockhash' => 'h',
                    ],
                ],
                'meta' => [
                    'err' => null,
                    'fee' => 5_000,
                    'computeUnitsConsumed' => 99,
                    'logMessages' => [],
                    'innerInstructions' => [],
                ],
            ],
        ]);

        /** @var Connection $connection */
        $connection = $this->container->make(Connection::class);

        $decoded = $connection->decodeTransaction('sig-xyz');

        self::assertNotNull($decoded);
        self::assertSame(DecodedTransactionSource::Rpc, $decoded->source);
        self::assertSame('sig-xyz', $decoded->signature);
        self::assertSame(42, $decoded->slot);
        self::assertSame(99, $decoded->computeUnitsConsumed);
        self::assertCount(1, $decoded->instructions);
        self::assertSame('Program', $decoded->instructions[0]->programId);
    }

    #[Test]
    public function decode_transaction_returns_null_when_rpc_yields_no_record(): void
    {
        $this->fakeRpcByMethod(['getTransaction' => null]);

        /** @var Connection $connection */
        $connection = $this->container->make(Connection::class);

        self::assertNull($connection->decodeTransaction('missing'));
    }

    #[Test]
    public function decode_transaction_sends_correct_rpc_params(): void
    {
        $capturedBody = null;
        $this->fakeRpcByMethod([
            'getTransaction' => function (array $body) use (&$capturedBody): array {
                $capturedBody = $body;

                return [];
            },
        ]);

        /** @var Connection $connection */
        $connection = $this->container->make(Connection::class);
        $connection->decodeTransaction('sig');

        self::assertNotNull($capturedBody);
        /** @var array{params: list<mixed>} $capturedBody */
        self::assertSame('sig', $capturedBody['params'][0]);
        self::assertSame('json', $capturedBody['params'][1]['encoding']);
        self::assertSame(0, $capturedBody['params'][1]['maxSupportedTransactionVersion']);
    }
}
