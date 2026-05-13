<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Exceptions;

use SanderMuller\SolanaPhpSdk\TransactionBuilder;

/**
 * Raised by {@see TransactionBuilder} when the
 * builder detects a transaction that would fail Solana's runtime "sanitize"
 * pass — duplicate accounts with conflicting writable/signer flags, missing
 * fee payer, signer-marked account without a corresponding keypair, etc.
 *
 * Carries `$reasons` so callers can surface multiple issues at once instead
 * of the one-at-a-time "Transaction failed to sanitize accounts offsets
 * correctly" error the validator returns. That validator error is the
 * single most-reported footgun across solana-go (#134, #199), solana-py,
 * and solanaj (#23); the builder's whole purpose is catching the
 * misconfiguration locally before the RPC round-trip.
 */
final class UnsanitizedTransactionException extends SolanaPhpSdkException
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct("Transaction would not sanitize:\n - " . implode("\n - ", $reasons));
    }
}
