<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Exceptions;

/**
 * Raised by `Connection::confirmTransaction` when the transaction's
 * blockhash has aged past the `lastValidBlockHeight` watermark before the
 * desired commitment was reached. Re-fetch a fresh blockhash and re-sign;
 * the previous transaction will never land.
 *
 * Extends `GenericException` for backwards compatibility with existing
 * call sites that catch the generic type.
 */
final class BlockhashExpiredException extends GenericException
{
    public function __construct(public readonly string $signature, string $message)
    {
        parent::__construct($message);
    }
}
