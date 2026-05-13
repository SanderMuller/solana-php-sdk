<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Exceptions;

/**
 * Raised by `Connection::confirmTransaction` when polling exceeds the
 * configured timeout WITHOUT the blockhash expiring. The transaction is
 * still in flight — re-query `getSignatureStatuses` later or accept the
 * indeterminate result.
 */
final class ConfirmationTimeoutException extends GenericException
{
    public function __construct(
        public readonly string $signature,
        public readonly int $timeoutSeconds,
        string $message,
    ) {
        parent::__construct($message);
    }
}
