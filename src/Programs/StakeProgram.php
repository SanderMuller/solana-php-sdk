<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\Enum\StakeAuthorize;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;

/**
 * Builders for the native Stake program.
 *
 * Discriminators (u32 LE) match
 * `solana_stake_interface::instruction::StakeInstruction`. Sysvar account
 * layouts are positional; the runtime resolves them by `Pubkey`.
 *
 * @see https://docs.solanalabs.com/runtime/programs#stake-program
 */
final class StakeProgram
{
    public const string PROGRAM_ID = 'Stake11111111111111111111111111111111111111';

    public const string SYSVAR_CLOCK = 'SysvarC1ock11111111111111111111111111111111';

    public const string SYSVAR_RENT = 'SysvarRent111111111111111111111111111111111';

    public const string SYSVAR_STAKE_HISTORY = 'SysvarStakeHistory1111111111111111111111111';

    public const string STAKE_CONFIG = 'StakeConfig11111111111111111111111111111111';

    /** @deprecated Use {@see StakeAuthorize::STAKER}. */
    public const int AUTHORIZE_STAKER = 0;

    /** @deprecated Use {@see StakeAuthorize::WITHDRAWER}. */
    public const int AUTHORIZE_WITHDRAWER = 1;

    private const int IX_INITIALIZE = 0;

    private const int IX_AUTHORIZE = 1;

    private const int IX_DELEGATE = 2;

    private const int IX_SPLIT = 3;

    private const int IX_WITHDRAW = 4;

    private const int IX_DEACTIVATE = 5;

    private const int IX_MERGE = 7;

    public static function programId(): PublicKey
    {
        return PublicKey::from(self::PROGRAM_ID);
    }

    /**
     * Initialize a freshly allocated stake account. `Authorized` carries the
     * staker + withdrawer authorities; `Lockup` defaults to all-zero (no
     * lockup) when both `unixTimestamp` and `epoch` are 0 and `custodian` is
     * the default pubkey.
     */
    public static function initialize(
        PublicKey $stakeAccount,
        PublicKey $staker,
        PublicKey $withdrawer,
        int $lockupUnixTimestamp = 0,
        int $lockupEpoch = 0,
        ?PublicKey $custodian = null,
    ): TransactionInstruction {
        $data = self::u32(self::IX_INITIALIZE);
        // Authorized: { staker, withdrawer }
        foreach ($staker->toBytes() as $byte) {
            $data[] = $byte;
        }

        foreach ($withdrawer->toBytes() as $byte) {
            $data[] = $byte;
        }

        // Lockup: { unix_timestamp (i64), epoch (u64), custodian }
        foreach (self::i64($lockupUnixTimestamp) as $byte) {
            $data[] = $byte;
        }

        foreach (self::u64($lockupEpoch) as $byte) {
            $data[] = $byte;
        }

        foreach (($custodian ?? PublicKey::default())->toBytes() as $byte) {
            $data[] = $byte;
        }

        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($stakeAccount, isSigner: false, isWritable: true),
                new AccountMeta(PublicKey::from(self::SYSVAR_RENT), isSigner: false, isWritable: false),
            ],
            data: $data,
        );
    }

    /**
     * Re-assign the staker or withdrawer authority. Pass {@see StakeAuthorize}
     * for type safety; raw `int` is still accepted for legacy call sites.
     */
    public static function authorize(
        PublicKey $stakeAccount,
        PublicKey $currentAuthority,
        PublicKey $newAuthority,
        StakeAuthorize|int $stakeAuthorize,
        ?PublicKey $custodian = null,
    ): TransactionInstruction {
        $role = $stakeAuthorize instanceof StakeAuthorize ? $stakeAuthorize->value : $stakeAuthorize;
        $data = self::u32(self::IX_AUTHORIZE);
        foreach ($newAuthority->toBytes() as $byte) {
            $data[] = $byte;
        }

        foreach (self::u32($role) as $byte) {
            $data[] = $byte;
        }

        $keys = [
            new AccountMeta($stakeAccount, isSigner: false, isWritable: true),
            new AccountMeta(PublicKey::from(self::SYSVAR_CLOCK), isSigner: false, isWritable: false),
            new AccountMeta($currentAuthority, isSigner: true, isWritable: false),
        ];

        if ($custodian instanceof PublicKey) {
            $keys[] = new AccountMeta($custodian, isSigner: true, isWritable: false);
        }

        return new TransactionInstruction(
            programId: self::programId(),
            keys: $keys,
            data: $data,
        );
    }

    public static function delegateStake(
        PublicKey $stakeAccount,
        PublicKey $voteAccount,
        PublicKey $stakeAuthority,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($stakeAccount, isSigner: false, isWritable: true),
                new AccountMeta($voteAccount, isSigner: false, isWritable: false),
                new AccountMeta(PublicKey::from(self::SYSVAR_CLOCK), isSigner: false, isWritable: false),
                new AccountMeta(PublicKey::from(self::SYSVAR_STAKE_HISTORY), isSigner: false, isWritable: false),
                new AccountMeta(PublicKey::from(self::STAKE_CONFIG), isSigner: false, isWritable: false),
                new AccountMeta($stakeAuthority, isSigner: true, isWritable: false),
            ],
            data: self::u32(self::IX_DELEGATE),
        );
    }

    public static function split(
        PublicKey $sourceStake,
        PublicKey $destinationStake,
        PublicKey $stakeAuthority,
        int $lamports,
    ): TransactionInstruction {
        $data = self::u32(self::IX_SPLIT);
        foreach (self::u64($lamports) as $byte) {
            $data[] = $byte;
        }

        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($sourceStake, isSigner: false, isWritable: true),
                new AccountMeta($destinationStake, isSigner: false, isWritable: true),
                new AccountMeta($stakeAuthority, isSigner: true, isWritable: false),
            ],
            data: $data,
        );
    }

    public static function withdraw(
        PublicKey $stakeAccount,
        PublicKey $recipient,
        PublicKey $withdrawAuthority,
        int $lamports,
        ?PublicKey $custodian = null,
    ): TransactionInstruction {
        $data = self::u32(self::IX_WITHDRAW);
        foreach (self::u64($lamports) as $byte) {
            $data[] = $byte;
        }

        $keys = [
            new AccountMeta($stakeAccount, isSigner: false, isWritable: true),
            new AccountMeta($recipient, isSigner: false, isWritable: true),
            new AccountMeta(PublicKey::from(self::SYSVAR_CLOCK), isSigner: false, isWritable: false),
            new AccountMeta(PublicKey::from(self::SYSVAR_STAKE_HISTORY), isSigner: false, isWritable: false),
            new AccountMeta($withdrawAuthority, isSigner: true, isWritable: false),
        ];

        if ($custodian instanceof PublicKey) {
            $keys[] = new AccountMeta($custodian, isSigner: true, isWritable: false);
        }

        return new TransactionInstruction(
            programId: self::programId(),
            keys: $keys,
            data: $data,
        );
    }

    public static function deactivate(
        PublicKey $stakeAccount,
        PublicKey $stakeAuthority,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($stakeAccount, isSigner: false, isWritable: true),
                new AccountMeta(PublicKey::from(self::SYSVAR_CLOCK), isSigner: false, isWritable: false),
                new AccountMeta($stakeAuthority, isSigner: true, isWritable: false),
            ],
            data: self::u32(self::IX_DEACTIVATE),
        );
    }

    /**
     * Merge `$sourceStake` into `$destinationStake`. Both accounts must share
     * the same vote delegation and credit/authority configuration; the source
     * is drained and effectively closed.
     */
    public static function merge(
        PublicKey $destinationStake,
        PublicKey $sourceStake,
        PublicKey $stakeAuthority,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($destinationStake, isSigner: false, isWritable: true),
                new AccountMeta($sourceStake, isSigner: false, isWritable: true),
                new AccountMeta(PublicKey::from(self::SYSVAR_CLOCK), isSigner: false, isWritable: false),
                new AccountMeta(PublicKey::from(self::SYSVAR_STAKE_HISTORY), isSigner: false, isWritable: false),
                new AccountMeta($stakeAuthority, isSigner: true, isWritable: false),
            ],
            data: self::u32(self::IX_MERGE),
        );
    }

    /**
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
    private static function i64(int $value): array
    {
        // PHP int is already signed 64-bit on 64-bit builds; the bit layout
        // for negative values matches Rust's two's complement i64.
        return self::u64($value);
    }
}
