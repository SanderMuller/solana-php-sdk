<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Errors;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\SolanaPhpSdk\Errors\TransactionErrorDecoder;
use SanderMuller\SolanaPhpSdk\Exceptions\RpcException;
use SanderMuller\SolanaPhpSdk\Exceptions\SendTransactionError;

/** @internal */
final class TransactionErrorDecoderTest extends TestCase
{
    #[Test]
    public function decodes_unit_variants_from_string(): void
    {
        $err = TransactionErrorDecoder::decode('BlockhashNotFound');

        self::assertSame('BlockhashNotFound', $err->kind);
        self::assertTrue($err->isBlockhashNotFound());
    }

    #[Test]
    public function decodes_instruction_error_with_custom_code(): void
    {
        $raw = ['InstructionError' => [2, ['Custom' => 6000]]];

        $err = TransactionErrorDecoder::decode($raw);

        self::assertTrue($err->isInstructionError());
        self::assertSame(2, $err->instructionIndex);
        self::assertNotNull($err->instructionError);
        self::assertTrue($err->isCustomError());
        self::assertSame(6000, $err->customCode());
        self::assertSame('InstructionError(2, Custom(6000))', (string) $err);
    }

    #[Test]
    public function decodes_instruction_error_unit_inner(): void
    {
        $raw = ['InstructionError' => [0, 'InsufficientFunds']];

        $err = TransactionErrorDecoder::decode($raw);

        self::assertSame(0, $err->instructionIndex);
        self::assertNotNull($err->instructionError);
        self::assertSame('InsufficientFunds', $err->instructionError->kind);
        self::assertFalse($err->isCustomError());
    }

    #[Test]
    public function decodes_duplicate_instruction(): void
    {
        $err = TransactionErrorDecoder::decode(['DuplicateInstruction' => 3]);

        self::assertSame('DuplicateInstruction', $err->kind);
        self::assertSame(3, $err->instructionIndex);
    }

    #[Test]
    public function decodes_insufficient_funds_for_rent_account_index(): void
    {
        $err = TransactionErrorDecoder::decode([
            'InsufficientFundsForRent' => ['account_index' => 4],
        ]);

        self::assertSame('InsufficientFundsForRent', $err->kind);
        self::assertSame(4, $err->accountIndex);
    }

    #[Test]
    public function decodes_borsh_io_error_message(): void
    {
        $err = TransactionErrorDecoder::decode([
            'InstructionError' => [1, ['BorshIoError' => 'unexpected length of input']],
        ]);

        self::assertNotNull($err->instructionError);
        self::assertSame('BorshIoError', $err->instructionError->kind);
        self::assertSame('unexpected length of input', $err->instructionError->message);
    }

    #[Test]
    public function send_transaction_error_from_rpc_carries_logs(): void
    {
        $rpc = new RpcException(
            message: 'Transaction simulation failed: Error processing Instruction 0: custom program error: 0x1',
            rpcCode: -32002,
            rpcData: [
                'err' => ['InstructionError' => [0, ['Custom' => 1]]],
                'logs' => ['Program 11111111111111111111111111111111 invoke [1]', 'Program log: oops'],
                'unitsConsumed' => 412,
                'accounts' => null,
            ],
        );

        $structured = SendTransactionError::tryFromRpc($rpc);

        self::assertNotNull($structured);
        self::assertSame(412, $structured->unitsConsumed);
        self::assertCount(2, $structured->logs);
        self::assertSame(1, $structured->transactionError->customCode());
    }

    #[Test]
    public function send_transaction_error_returns_null_when_no_err_payload(): void
    {
        $rpc = new RpcException('boom', -32000);

        self::assertNull(SendTransactionError::tryFromRpc($rpc));
    }

    #[Test]
    public function send_transaction_error_returns_null_when_err_is_explicitly_null(): void
    {
        // RPC response with `err: null` carries no transaction failure —
        // it must NOT be decoded as `TransactionError('Unknown')`.
        $rpc = new RpcException('weird provider payload', -32000, ['err' => null, 'logs' => []]);

        self::assertNull(SendTransactionError::tryFromRpc($rpc));
    }
}
