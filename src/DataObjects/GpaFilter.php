<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

/**
 * Typed builders for the two `getProgramAccounts` server-side filter
 * shapes. Returning a plain array keeps `getProgramAccounts($filters)`
 * BC; new code should prefer these constructors so the shape can never
 * drift (the offset/length pair has tripped up every Solana SDK at some
 * point: solana-go #134/#199, solana-py).
 *
 * @see https://solana.com/docs/rpc/http/getprogramaccounts#filter-criteria
 */
final class GpaFilter
{
    /**
     * Match accounts whose data at `$offset` equals `$bytes`. Pass either
     * a raw byte string, a {@see Buffer}, or a {@see PublicKey} (which is
     * automatically base58-encoded — the canonical form for pubkey memcmp
     * filters).
     *
     * @return array{memcmp: array{offset: int, bytes: string, encoding: string}}
     *
     * @throws InputValidationException
     */
    public static function memcmp(int $offset, Buffer|PublicKey|string $bytes, string $encoding = 'base58'): array
    {
        if ($offset < 0) {
            throw new InputValidationException('memcmp offset must be non-negative.');
        }

        $encoded = match (true) {
            $bytes instanceof PublicKey => $bytes->toBase58(),
            $bytes instanceof Buffer => match ($encoding) {
                'base58' => $bytes->toBase58String(),
                'base64' => base64_encode($bytes->toBinaryString()),
                default => throw new InputValidationException("Unsupported memcmp encoding: {$encoding}"),
            },
            default => $bytes,
        };

        return [
            'memcmp' => [
                'offset' => $offset,
                'bytes' => $encoded,
                'encoding' => $bytes instanceof PublicKey ? 'base58' : $encoding,
            ],
        ];
    }

    /**
     * Match accounts whose serialized data length is exactly `$size`.
     *
     * @return array{dataSize: int}
     *
     * @throws InputValidationException
     */
    public static function dataSize(int $size): array
    {
        if ($size < 0) {
            throw new InputValidationException('dataSize must be non-negative.');
        }

        return ['dataSize' => $size];
    }
}
