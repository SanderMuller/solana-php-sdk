<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Exceptions;

/**
 * Raised when an on-chain transaction landed in a block with a non-null
 * `err` payload — i.e. the runtime rejected one of the instructions. Never
 * retryable as-is; inspect `$err` and fix the inputs.
 */
final class TransactionFailedOnChainException extends GenericException
{
    public function __construct(
        public readonly string $signature,
        public readonly mixed $err,
        string $message,
    ) {
        parent::__construct($message);
    }
}
