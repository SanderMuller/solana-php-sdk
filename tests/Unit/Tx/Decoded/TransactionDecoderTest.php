<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Tx\Decoded;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\DecodedTransactionSource;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\TransactionDecoder;
use StephenHill\Base58;

/** @internal */
final class TransactionDecoderTest extends TestCase
{
    #[Test]
    public function decodes_a_legacy_sol_transfer(): void
    {
        $rpc = [
            'slot' => 100,
            'blockTime' => 1_700_000_000,
            'transaction' => [
                'signatures' => ['sig-abc'],
                'message' => [
                    'header' => [
                        'numRequiredSignatures' => 1,
                        'numReadonlySignedAccounts' => 0,
                        'numReadonlyUnsignedAccounts' => 1,
                    ],
                    'accountKeys' => [
                        'Alice1111111111111111111111111111111111111111',
                        'Bob111111111111111111111111111111111111111111',
                        '11111111111111111111111111111111',
                    ],
                    'instructions' => [
                        [
                            'programIdIndex' => 2,
                            'accounts' => [0, 1],
                            'data' => '3Bxs411Dtc7pkFQj', // ignored shape in v1
                        ],
                    ],
                    'recentBlockhash' => 'hash',
                ],
            ],
            'meta' => [
                'err' => null,
                'fee' => 5_000,
                'computeUnitsConsumed' => 150,
                'logMessages' => [
                    'Program 11111111111111111111111111111111 invoke [1]',
                    'Program 11111111111111111111111111111111 success',
                ],
                'innerInstructions' => [],
            ],
        ];

        $decoded = TransactionDecoder::fromRpcResponse($rpc);

        self::assertSame(DecodedTransactionSource::Rpc, $decoded->source);
        self::assertSame('sig-abc', $decoded->signature);
        self::assertSame(100, $decoded->slot);
        self::assertSame(5_000, $decoded->fee);
        self::assertSame(150, $decoded->computeUnitsConsumed);

        self::assertSame(['Alice1111111111111111111111111111111111111111'], $decoded->signers);
        self::assertContains(0, $decoded->writableIndexes); // payer
        self::assertContains(1, $decoded->writableIndexes); // recipient
        self::assertNotContains(2, $decoded->writableIndexes); // system program is readonly

        self::assertCount(1, $decoded->instructions);
        $ix = $decoded->instructions[0];
        self::assertSame('11111111111111111111111111111111', $ix->programId);
        self::assertSame('0', $ix->path);
        self::assertCount(2, $ix->accounts);
        self::assertSame('Alice1111111111111111111111111111111111111111', $ix->accounts[0]->pubkey);
        self::assertTrue($ix->accounts[0]->isSigner);
        self::assertTrue($ix->accounts[0]->isWritable);
        self::assertFalse($ix->accounts[1]->isSigner);
        self::assertTrue($ix->accounts[1]->isWritable);
    }

    #[Test]
    public function decodes_inner_instructions_with_dotted_paths(): void
    {
        $rpc = [
            'slot' => 1,
            'blockTime' => null,
            'transaction' => [
                'signatures' => ['sig'],
                'message' => [
                    'header' => [
                        'numRequiredSignatures' => 1,
                        'numReadonlySignedAccounts' => 0,
                        'numReadonlyUnsignedAccounts' => 1,
                    ],
                    'accountKeys' => ['Payer', 'Recipient', 'Program'],
                    'instructions' => [
                        ['programIdIndex' => 2, 'accounts' => [0, 1], 'data' => ''],
                    ],
                    'recentBlockhash' => 'h',
                ],
            ],
            'meta' => [
                'err' => null,
                'fee' => 5_000,
                'computeUnitsConsumed' => 100,
                'logMessages' => [],
                'innerInstructions' => [
                    [
                        'index' => 0,
                        'instructions' => [
                            ['programIdIndex' => 2, 'accounts' => [0], 'data' => '', 'stackHeight' => 2],
                            ['programIdIndex' => 2, 'accounts' => [1], 'data' => '', 'stackHeight' => 2],
                        ],
                    ],
                ],
            ],
        ];

        $decoded = TransactionDecoder::fromRpcResponse($rpc);

        $top = $decoded->instructions[0];
        self::assertCount(2, $top->innerInstructions);
        self::assertSame('0.0', $top->innerInstructions[0]->path);
        self::assertSame('0.1', $top->innerInstructions[1]->path);
        self::assertSame(2, $top->innerInstructions[0]->stackHeight);
    }

    #[Test]
    public function expands_v0_address_lookup_table_accounts_in_static_writable_readonly_order(): void
    {
        $rpc = [
            'slot' => 1,
            'blockTime' => null,
            'transaction' => [
                'signatures' => ['sig'],
                'message' => [
                    'header' => [
                        'numRequiredSignatures' => 1,
                        'numReadonlySignedAccounts' => 0,
                        'numReadonlyUnsignedAccounts' => 1,
                    ],
                    'accountKeys' => ['Payer', 'Program'],
                    'addressTableLookups' => [['accountKey' => 'TableXYZ', 'writableIndexes' => [0], 'readonlyIndexes' => [3]]],
                    'instructions' => [
                        // touches the loaded writable (index 2) AND loaded readonly (index 3)
                        ['programIdIndex' => 1, 'accounts' => [0, 2, 3], 'data' => ''],
                    ],
                    'recentBlockhash' => 'h',
                ],
            ],
            'meta' => [
                'err' => null,
                'fee' => 5_000,
                'computeUnitsConsumed' => 80,
                'logMessages' => [],
                'innerInstructions' => [],
                'loadedAddresses' => [
                    'writable' => ['AltWritableAcct'],
                    'readonly' => ['AltReadonlyAcct'],
                ],
            ],
        ];

        $decoded = TransactionDecoder::fromRpcResponse($rpc);

        // Resolved key list order: 2 static + 1 loaded-writable + 1 loaded-readonly.
        self::assertSame(['Payer', 'Program', 'AltWritableAcct', 'AltReadonlyAcct'], $decoded->accountKeys);
        self::assertSame(['AltWritableAcct'], $decoded->loadedWritableAddresses);
        self::assertSame(['AltReadonlyAcct'], $decoded->loadedReadonlyAddresses);

        $ix = $decoded->instructions[0];
        self::assertSame('Payer', $ix->accounts[0]->pubkey);
        self::assertTrue($ix->accounts[0]->isSigner);

        self::assertSame('AltWritableAcct', $ix->accounts[1]->pubkey);
        self::assertTrue($ix->accounts[1]->isWritable);
        self::assertFalse($ix->accounts[1]->isSigner);

        self::assertSame('AltReadonlyAcct', $ix->accounts[2]->pubkey);
        self::assertFalse($ix->accounts[2]->isWritable);
        self::assertFalse($ix->accounts[2]->isSigner);
    }

    #[Test]
    public function survives_meta_null_response(): void
    {
        $rpc = [
            'slot' => 1,
            'blockTime' => null,
            'transaction' => [
                'signatures' => ['sig'],
                'message' => [
                    'header' => [
                        'numRequiredSignatures' => 1,
                        'numReadonlySignedAccounts' => 0,
                        'numReadonlyUnsignedAccounts' => 0,
                    ],
                    'accountKeys' => ['Alice'],
                    'instructions' => [],
                    'recentBlockhash' => 'h',
                ],
            ],
            'meta' => null,
        ];

        $decoded = TransactionDecoder::fromRpcResponse($rpc);

        self::assertNull($decoded->meta);
        self::assertNull($decoded->fee);
        self::assertNull($decoded->computeUnitsConsumed);
        self::assertSame([], $decoded->logs);
        self::assertSame([], $decoded->loadedWritableAddresses);
    }

    #[Test]
    public function instruction_data_decodes_base58_to_binary(): void
    {
        $rpc = [
            'slot' => 1,
            'blockTime' => null,
            'transaction' => [
                'signatures' => ['sig'],
                'message' => [
                    'header' => [
                        'numRequiredSignatures' => 1,
                        'numReadonlySignedAccounts' => 0,
                        'numReadonlyUnsignedAccounts' => 1,
                    ],
                    'accountKeys' => ['Payer', 'Program'],
                    'instructions' => [
                        ['programIdIndex' => 1, 'accounts' => [], 'data' => (new Base58())->encode("\x02\xff\x00")],
                    ],
                    'recentBlockhash' => 'h',
                ],
            ],
            'meta' => ['err' => null, 'fee' => 5_000, 'computeUnitsConsumed' => 1, 'logMessages' => [], 'innerInstructions' => []],
        ];

        $decoded = TransactionDecoder::fromRpcResponse($rpc);
        $ix = $decoded->instructions[0];

        self::assertSame("\x02\xff\x00", $ix->data);
        self::assertNotSame('', $ix->dataBase58);
    }

    #[Test]
    public function err_field_passes_through_for_failed_transactions(): void
    {
        $rpc = [
            'slot' => 1,
            'blockTime' => null,
            'transaction' => [
                'signatures' => ['sig'],
                'message' => [
                    'header' => ['numRequiredSignatures' => 1, 'numReadonlySignedAccounts' => 0, 'numReadonlyUnsignedAccounts' => 0],
                    'accountKeys' => ['A'],
                    'instructions' => [],
                    'recentBlockhash' => 'h',
                ],
            ],
            'meta' => ['err' => ['InstructionError' => [0, ['Custom' => 1]]], 'fee' => 5_000, 'computeUnitsConsumed' => 0, 'logMessages' => [], 'innerInstructions' => []],
        ];

        $decoded = TransactionDecoder::fromRpcResponse($rpc);

        self::assertSame(['InstructionError' => [0, ['Custom' => 1]]], $decoded->err);
    }

    #[Test]
    public function signers_collection_matches_static_writable_signed_accounts(): void
    {
        // Two required signers, one of them readonly. Recipient is non-signer writable.
        $rpc = [
            'slot' => 1,
            'blockTime' => null,
            'transaction' => [
                'signatures' => ['sig1', 'sig2'],
                'message' => [
                    'header' => [
                        'numRequiredSignatures' => 2,
                        'numReadonlySignedAccounts' => 1,
                        'numReadonlyUnsignedAccounts' => 1,
                    ],
                    'accountKeys' => ['WritableSigner', 'ReadonlySigner', 'Recipient', 'Program'],
                    'instructions' => [],
                    'recentBlockhash' => 'h',
                ],
            ],
            'meta' => ['err' => null, 'fee' => 0, 'computeUnitsConsumed' => 0, 'logMessages' => [], 'innerInstructions' => []],
        ];

        $decoded = TransactionDecoder::fromRpcResponse($rpc);

        self::assertSame(['WritableSigner', 'ReadonlySigner'], $decoded->signers);
        // WritableSigner (0) + Recipient (2) writable; ReadonlySigner (1) + Program (3) readonly.
        self::assertSame([0, 2], $decoded->writableIndexes);
    }
}
