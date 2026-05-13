<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\PublicKey;

/**
 * One-liner program-derived-address helper. Wraps
 * {@see PublicKey::findProgramAddress()} for the common case of
 * "derive the canonical PDA for these seeds under this program."
 *
 * Returns a two-element array: `[$address, $bump]`. Pass the bump back
 * to on-chain code that needs to validate the seed search converged at
 * the same nonce.
 *
 * @api
 */
final class Pda
{
    /**
     * @param array<int, string|PublicKey|Buffer> $seeds may be raw byte
     *        strings (passed through), PublicKey instances (converted via
     *        `getBuffer()`), or Buffer instances (kept as-is).
     *
     * @return array{0: PublicKey, 1: int}
     */
    public static function find(string|PublicKey $programId, array $seeds): array
    {
        $programId = $programId instanceof PublicKey ? $programId : PublicKey::from($programId);

        $normalized = [];
        foreach ($seeds as $seed) {
            $normalized[] = $seed instanceof PublicKey ? $seed->getBuffer() : $seed;
        }

        return PublicKey::findProgramAddress($normalized, $programId);
    }

    /**
     * Same as {@see find()} but returns only the address — drops the
     * bump byte. Convenient when you do not need to re-validate the
     * derivation on-chain.
     *
     * @param array<int, string|PublicKey|Buffer> $seeds
     */
    public static function address(string|PublicKey $programId, array $seeds): PublicKey
    {
        [$address] = self::find($programId, $seeds);

        return $address;
    }
}
