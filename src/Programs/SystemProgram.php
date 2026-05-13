<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;

/**
 * Builders for instructions in the native Solana System program.
 *
 * RPC-style helpers (`getAccountInfo`, `getBalance`, `getTransaction`) have
 * been removed in favour of the single source of truth on {@see Connection}.
 * This class now only emits {@see TransactionInstruction}s.
 *
 * Discriminators match `solana_sdk::system_instruction::SystemInstruction`.
 */
final class SystemProgram implements Program
{
    use IsProgram;

    public const int PROGRAM_INDEX_CREATE_ACCOUNT = 0;

    public const int PROGRAM_INDEX_ASSIGN = 1;

    public const int PROGRAM_INDEX_TRANSFER = 2;

    public const int PROGRAM_INDEX_CREATE_ACCOUNT_WITH_SEED = 3;

    public const int PROGRAM_INDEX_ADVANCE_NONCE_ACCOUNT = 4;

    public const int PROGRAM_INDEX_WITHDRAW_NONCE_ACCOUNT = 5;

    public const int PROGRAM_INDEX_INITIALIZE_NONCE_ACCOUNT = 6;

    public const int PROGRAM_INDEX_AUTHORIZE_NONCE_ACCOUNT = 7;

    public const int PROGRAM_INDEX_ALLOCATE = 8;

    public const int PROGRAM_INDEX_ALLOCATE_WITH_SEED = 9;

    public const int PROGRAM_INDEX_ASSIGN_WITH_SEED = 10;

    public const int PROGRAM_INDEX_TRANSFER_WITH_SEED = 11;

    public const string RECENT_BLOCKHASHES_PUBKEY = 'SysvarRecentB1ockHashes11111111111111111111';

    public const string RENT_PUBKEY = 'SysvarRent111111111111111111111111111111111';

    private const array CONSTANTS = [
        'SYSTEM_PROGRAM_ID' => '11111111111111111111111111111111',
        'TOKEN_PROGRAM_ID' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
        'ASSOCIATED_TOKEN_PROGRAM_ID' => 'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL',
        'TOKEN_2022_PROGRAM_ID' => 'TokenzQdBNbLqP5VEhdkAS6EPFLC1PHnBqCXEpPxuEb',
        'MEMO_PROGRAM_ID' => 'MemoSq4gqABAXKb96qnH8TysNcWxMyWCqXgDLGmfcHr',
    ];

    /**
     * Look up a well-known program-id constant. Returns null for unknown keys.
     */
    public function config(string $key): mixed
    {
        return self::CONSTANTS[$key] ?? null;
    }

    public static function programId(): PublicKey
    {
        return PublicKey::default();
    }

    /**
     * Transfer lamports from $fromPubkey to $toPublicKey.
     */
    public static function transfer(
        PublicKey $fromPubkey,
        PublicKey $toPublicKey,
        int       $lamports,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($fromPubkey, true, true),
                new AccountMeta($toPublicKey, false, true),
            ],
            data: [
                ...self::u32(self::PROGRAM_INDEX_TRANSFER),
                ...self::u64($lamports),
            ],
        );
    }

    /**
     * Create a new account owned by $programId, funded with $lamports from $fromPubkey.
     */
    public static function createAccount(
        PublicKey $fromPubkey,
        PublicKey $newAccountPublicKey,
        int       $lamports,
        int       $space,
        PublicKey $programId,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($fromPubkey, true, true),
                new AccountMeta($newAccountPublicKey, true, true),
            ],
            data: [
                ...self::u32(self::PROGRAM_INDEX_CREATE_ACCOUNT),
                ...self::u64($lamports),
                ...self::u64($space),
                ...$programId->toBytes(),
            ],
        );
    }

    /**
     * Assign a new owner program to an existing account.
     */
    public static function assign(PublicKey $accountPubkey, PublicKey $programId): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [new AccountMeta($accountPubkey, true, true)],
            data: [
                ...self::u32(self::PROGRAM_INDEX_ASSIGN),
                ...$programId->toBytes(),
            ],
        );
    }

    /**
     * Allocate $space bytes of storage to $accountPubkey. Account must be owned
     * by the System program at the time of the call.
     */
    public static function allocate(PublicKey $accountPubkey, int $space): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [new AccountMeta($accountPubkey, true, true)],
            data: [
                ...self::u32(self::PROGRAM_INDEX_ALLOCATE),
                ...self::u64($space),
            ],
        );
    }

    /**
     * Transfer lamports from a derived account, signing with the seed authority.
     */
    public static function transferWithSeed(
        PublicKey $fromPubkey,
        PublicKey $basePubkey,
        string    $seed,
        PublicKey $fromOwner,
        PublicKey $toPublicKey,
        int       $lamports,
    ): TransactionInstruction {
        $seedBytes = self::stringToBytes($seed);

        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($fromPubkey, false, true),
                new AccountMeta($basePubkey, true, false),
                new AccountMeta($toPublicKey, false, true),
            ],
            data: [
                ...self::u32(self::PROGRAM_INDEX_TRANSFER_WITH_SEED),
                ...self::u64($lamports),
                ...self::u64(count($seedBytes)),
                ...$seedBytes,
                ...$fromOwner->toBytes(),
            ],
        );
    }

    /**
     * Initialize a nonce account with the given authority.
     */
    public static function initializeNonceAccount(
        PublicKey $noncePubkey,
        PublicKey $authorityPubkey,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($noncePubkey, false, true),
                new AccountMeta(PublicKey::from(self::RECENT_BLOCKHASHES_PUBKEY), false, false),
                new AccountMeta(PublicKey::from(self::RENT_PUBKEY), false, false),
            ],
            data: [
                ...self::u32(self::PROGRAM_INDEX_INITIALIZE_NONCE_ACCOUNT),
                ...$authorityPubkey->toBytes(),
            ],
        );
    }

    /**
     * Consume and replace the on-chain nonce value held by $noncePubkey.
     */
    public static function advanceNonceAccount(
        PublicKey $noncePubkey,
        PublicKey $authorizedPubkey,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($noncePubkey, false, true),
                new AccountMeta(PublicKey::from(self::RECENT_BLOCKHASHES_PUBKEY), false, false),
                new AccountMeta($authorizedPubkey, true, false),
            ],
            data: self::u32(self::PROGRAM_INDEX_ADVANCE_NONCE_ACCOUNT),
        );
    }

    /**
     * Withdraw lamports from a nonce account.
     */
    public static function withdrawNonceAccount(
        PublicKey $noncePubkey,
        PublicKey $authorizedPubkey,
        PublicKey $toPublicKey,
        int       $lamports,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($noncePubkey, false, true),
                new AccountMeta($toPublicKey, false, true),
                new AccountMeta(PublicKey::from(self::RECENT_BLOCKHASHES_PUBKEY), false, false),
                new AccountMeta(PublicKey::from(self::RENT_PUBKEY), false, false),
                new AccountMeta($authorizedPubkey, true, false),
            ],
            data: [
                ...self::u32(self::PROGRAM_INDEX_WITHDRAW_NONCE_ACCOUNT),
                ...self::u64($lamports),
            ],
        );
    }

    /**
     * Re-authorize a nonce account.
     */
    public static function authorizeNonceAccount(
        PublicKey $noncePubkey,
        PublicKey $authorizedPubkey,
        PublicKey $newAuthority,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($noncePubkey, false, true),
                new AccountMeta($authorizedPubkey, true, false),
            ],
            data: [
                ...self::u32(self::PROGRAM_INDEX_AUTHORIZE_NONCE_ACCOUNT),
                ...$newAuthority->toBytes(),
            ],
        );
    }

    /**
     * Little-endian u32 byte sequence. Hand-rolled rather than
     * `unpack('C*', pack('V', $v))` so PHPStan can prove the array shape
     * (the unpack/pack chain returns `array<int,int>|false`).
     *
     * @return array<int, int>
     */
    private static function u32(int $value): array
    {
        return [
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
        ];
    }

    /**
     * Little-endian u64 byte sequence. PHP ints are signed 64-bit; values
     * up to PHP_INT_MAX (2^63 - 1) emit a canonical u64 byte layout.
     *
     * @return array<int, int>
     */
    private static function u64(int $value): array
    {
        return [
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
            ($value >> 32) & 0xFF,
            ($value >> 40) & 0xFF,
            ($value >> 48) & 0xFF,
            ($value >> 56) & 0xFF,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function stringToBytes(string $value): array
    {
        $bytes = [];
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $bytes[] = ord($value[$i]);
        }

        return $bytes;
    }
}
