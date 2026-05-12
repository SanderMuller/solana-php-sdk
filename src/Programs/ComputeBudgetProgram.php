<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\Buffer;

/**
 * Builder for ComputeBudget program instructions.
 *
 * Discriminators match the on-chain program:
 *   1 = RequestHeapFrame(u32 bytes)
 *   2 = SetComputeUnitLimit(u32 units)
 *   3 = SetComputeUnitPrice(u64 micro-lamports per CU)
 *   4 = SetLoadedAccountsDataSizeLimit(u32 bytes)
 *
 * @see https://docs.solana.com/developing/runtime-facilities/programs#compute-budget
 */
final class ComputeBudgetProgram
{
    public const string PROGRAM_ID = 'ComputeBudget111111111111111111111111111111';

    private const int IX_REQUEST_HEAP_FRAME = 1;

    private const int IX_SET_COMPUTE_UNIT_LIMIT = 2;

    private const int IX_SET_COMPUTE_UNIT_PRICE = 3;

    private const int IX_SET_LOADED_ACCOUNTS_DATA_SIZE_LIMIT = 4;

    public static function programId(): PublicKey
    {
        return PublicKey::from(self::PROGRAM_ID);
    }

    /**
     * Request a non-default heap frame size in bytes. Must be a multiple of 1024
     * between 1KB and 256KB.
     */
    public static function requestHeapFrame(int $bytes): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [],
            data: Buffer::concat(
                Buffer::fromArray([self::IX_REQUEST_HEAP_FRAME]),
                self::packU32($bytes),
            ),
        );
    }

    /**
     * Set the maximum number of compute units this transaction may consume.
     * Required before raising the priority fee — without it, the runtime
     * assumes 200_000 CU per instruction (legacy default).
     */
    public static function setComputeUnitLimit(int $units): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [],
            data: Buffer::concat(
                Buffer::fromArray([self::IX_SET_COMPUTE_UNIT_LIMIT]),
                self::packU32($units),
            ),
        );
    }

    /**
     * Set the priority fee, denominated in micro-lamports per compute unit.
     */
    public static function setComputeUnitPrice(int $microLamports): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [],
            data: Buffer::concat(
                Buffer::fromArray([self::IX_SET_COMPUTE_UNIT_PRICE]),
                self::packU64($microLamports),
            ),
        );
    }

    /**
     * Cap the size of accounts loaded by the transaction, in bytes.
     */
    public static function setLoadedAccountsDataSizeLimit(int $bytes): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [],
            data: Buffer::concat(
                Buffer::fromArray([self::IX_SET_LOADED_ACCOUNTS_DATA_SIZE_LIMIT]),
                self::packU32($bytes),
            ),
        );
    }

    private static function packU32(int $value): Buffer
    {
        return Buffer::fromArray([
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
        ]);
    }

    private static function packU64(int $value): Buffer
    {
        return Buffer::fromArray([
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
            ($value >> 32) & 0xFF,
            ($value >> 40) & 0xFF,
            ($value >> 48) & 0xFF,
            ($value >> 56) & 0xFF,
        ]);
    }
}
