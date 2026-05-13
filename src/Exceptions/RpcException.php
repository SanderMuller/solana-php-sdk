<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Exceptions;

/**
 * Wraps a JSON-RPC `error` block returned by the Solana RPC. Carries
 * the numeric `code` and arbitrary `data` payload — most notably for
 * `sendTransaction` / `simulateTransaction`, where `data` holds
 * `err`, `logs`, `accounts`, `unitsConsumed`.
 *
 * Extends {@see GenericException} so existing catch blocks keep
 * working; richer subclasses (e.g. {@see SendTransactionError}) extend
 * this so the structured fields are still available to callers that
 * opt into them.
 */
class RpcException extends GenericException
{
    public function __construct(
        string $message,
        public readonly int $rpcCode,
        public readonly mixed $rpcData = null,
    ) {
        parent::__construct($message);
    }
}
