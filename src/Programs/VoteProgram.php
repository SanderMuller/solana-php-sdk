<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\Enum\VoteAuthorize;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;

/**
 * Operator-facing builders for the native Vote program. Focused on the
 * subset validators interact with directly — withdraw, authority change,
 * commission update, validator-identity update. Vote-submission itself is
 * driven by the validator binary, not application clients.
 *
 * Discriminators (u32 LE) match
 * `solana_vote_interface::instruction::VoteInstruction`.
 */
final class VoteProgram
{
    public const string PROGRAM_ID = 'Vote111111111111111111111111111111111111111';

    public const string SYSVAR_CLOCK = 'SysvarC1ock11111111111111111111111111111111';

    /** @deprecated Use {@see VoteAuthorize::VOTER}. */
    public const int AUTHORIZE_VOTER = 0;

    /** @deprecated Use {@see VoteAuthorize::WITHDRAWER}. */
    public const int AUTHORIZE_WITHDRAWER = 1;

    private const int IX_AUTHORIZE = 1;

    private const int IX_WITHDRAW = 3;

    private const int IX_UPDATE_VALIDATOR_IDENTITY = 4;

    private const int IX_UPDATE_COMMISSION = 5;

    public static function programId(): PublicKey
    {
        return PublicKey::from(self::PROGRAM_ID);
    }

    /**
     * Re-assign the voter or withdrawer authority. Pass {@see VoteAuthorize}
     * for type safety; raw `int` is still accepted for legacy call sites.
     */
    public static function authorize(
        PublicKey $voteAccount,
        PublicKey $currentAuthority,
        PublicKey $newAuthority,
        VoteAuthorize|int $voteAuthorize,
    ): TransactionInstruction {
        $role = $voteAuthorize instanceof VoteAuthorize ? $voteAuthorize->value : $voteAuthorize;
        $data = self::u32(self::IX_AUTHORIZE);
        foreach ($newAuthority->toBytes() as $byte) {
            $data[] = $byte;
        }

        foreach (self::u32($role) as $byte) {
            $data[] = $byte;
        }

        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($voteAccount, isSigner: false, isWritable: true),
                new AccountMeta(PublicKey::from(self::SYSVAR_CLOCK), isSigner: false, isWritable: false),
                new AccountMeta($currentAuthority, isSigner: true, isWritable: false),
            ],
            data: $data,
        );
    }

    public static function withdraw(
        PublicKey $voteAccount,
        PublicKey $recipient,
        PublicKey $withdrawAuthority,
        int $lamports,
    ): TransactionInstruction {
        $data = self::u32(self::IX_WITHDRAW);
        foreach (self::u64($lamports) as $byte) {
            $data[] = $byte;
        }

        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($voteAccount, isSigner: false, isWritable: true),
                new AccountMeta($recipient, isSigner: false, isWritable: true),
                new AccountMeta($withdrawAuthority, isSigner: true, isWritable: false),
            ],
            data: $data,
        );
    }

    public static function updateValidatorIdentity(
        PublicKey $voteAccount,
        PublicKey $newValidatorIdentity,
        PublicKey $withdrawAuthority,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($voteAccount, isSigner: false, isWritable: true),
                new AccountMeta($newValidatorIdentity, isSigner: true, isWritable: false),
                new AccountMeta($withdrawAuthority, isSigner: true, isWritable: false),
            ],
            data: self::u32(self::IX_UPDATE_VALIDATOR_IDENTITY),
        );
    }

    /**
     * `$commission` is the validator commission percentage (0–100).
     */
    public static function updateCommission(
        PublicKey $voteAccount,
        PublicKey $withdrawAuthority,
        int $commission,
    ): TransactionInstruction {
        $data = self::u32(self::IX_UPDATE_COMMISSION);
        $data[] = $commission & 0xFF;

        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($voteAccount, isSigner: false, isWritable: true),
                new AccountMeta($withdrawAuthority, isSigner: true, isWritable: false),
            ],
            data: $data,
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
}
