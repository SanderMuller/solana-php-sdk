<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\Exceptions\TokenOwnerOffCurveError;
use SanderMuller\SolanaPhpSdk\Programs\SplTokenProgram;
use SanderMuller\SolanaPhpSdk\Programs\Token2022Program;
use SanderMuller\SolanaPhpSdk\PublicKey;

/**
 * One-liner Associated Token Account derivation. Defaults to the legacy
 * SPL Token program; pass `tokenProgram: Token2022Program::TOKEN_PROGRAM_ID`
 * (or the constant directly) for Token-2022 mints.
 *
 * Throws {@see TokenOwnerOffCurveError} when the owner address is not on
 * the Ed25519 curve and `$allowOwnerOffCurve` is left at its default
 * false. Wallets are always on-curve; PDAs are not — so the guard
 * trips for PDA-owned ATAs by default, forcing callers to opt in.
 *
 * @api
 */
final class Ata
{
    public const string ASSOCIATED_TOKEN_PROGRAM_ID = 'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL';

    public static function derive(
        string|PublicKey $owner,
        string|PublicKey $mint,
        string|PublicKey $tokenProgram = SplTokenProgram::TOKEN_PROGRAM_ID,
        bool $allowOwnerOffCurve = false,
        string|PublicKey $associatedTokenProgram = self::ASSOCIATED_TOKEN_PROGRAM_ID,
    ): PublicKey {
        $owner = $owner instanceof PublicKey ? $owner : PublicKey::from($owner);
        $mint = $mint instanceof PublicKey ? $mint : PublicKey::from($mint);
        $tokenProgram = $tokenProgram instanceof PublicKey ? $tokenProgram : PublicKey::from($tokenProgram);
        $atProgram = $associatedTokenProgram instanceof PublicKey
            ? $associatedTokenProgram
            : PublicKey::from($associatedTokenProgram);

        if (! $allowOwnerOffCurve && ! PublicKey::isOnCurve($owner->toBinaryString())) {
            throw new TokenOwnerOffCurveError();
        }

        return Pda::address($atProgram, [
            $owner->getBuffer(),
            $tokenProgram->getBuffer(),
            $mint->getBuffer(),
        ]);
    }

    /**
     * Token-2022 shortcut — same as {@see derive()} with the
     * `tokenProgram` argument pre-bound to Token-2022's program id.
     */
    public static function derive2022(
        string|PublicKey $owner,
        string|PublicKey $mint,
        bool $allowOwnerOffCurve = false,
    ): PublicKey {
        return self::derive(
            owner: $owner,
            mint: $mint,
            tokenProgram: Token2022Program::TOKEN_PROGRAM_ID,
            allowOwnerOffCurve: $allowOwnerOffCurve,
        );
    }
}
