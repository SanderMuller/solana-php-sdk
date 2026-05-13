<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Exceptions;

use SanderMuller\SolanaPhpSdk\Errors\TransactionError;
use SanderMuller\SolanaPhpSdk\Errors\TransactionErrorDecoder;

/**
 * Structured preflight / simulation failure from `sendTransaction` /
 * `simulateTransaction`. Carries the decoded {@see TransactionError},
 * the program logs collected before the failure, the units consumed
 * (when supplied), and the verbatim RPC `data` payload for callers
 * that want to inspect anything unanticipated.
 *
 * Extends {@see RpcException} → {@see GenericException}, so any
 * pre-existing `catch (GenericException $e)` block still catches it.
 * Opt-in handlers can switch on {@see $transactionError->kind} to
 * distinguish `BlockhashNotFound` from program-custom errors etc.
 */
final class SendTransactionError extends RpcException
{
    /**
     * @param list<string> $logs
     */
    public function __construct(
        string $message,
        int $rpcCode,
        mixed $rpcData,
        public readonly TransactionError $transactionError,
        public readonly array $logs = [],
        public readonly ?int $unitsConsumed = null,
    ) {
        parent::__construct($message, $rpcCode, $rpcData);
    }

    /**
     * Build a typed exception from a base {@see RpcException} when the
     * `data` payload carries an `err` block. Returns null when the
     * payload is empty or shaped differently — callers fall back to the
     * raw RpcException in that case.
     */
    public static function tryFromRpc(RpcException $e): ?self
    {
        $data = $e->rpcData;
        if (! is_array($data) || ! array_key_exists('err', $data) || $data['err'] === null) {
            return null;
        }

        $logs = [];
        if (isset($data['logs']) && is_array($data['logs'])) {
            foreach ($data['logs'] as $log) {
                if (is_string($log)) {
                    $logs[] = $log;
                }
            }
        }

        $units = isset($data['unitsConsumed']) && is_int($data['unitsConsumed'])
            ? $data['unitsConsumed']
            : null;

        return new self(
            message: $e->getMessage(),
            rpcCode: $e->rpcCode,
            rpcData: $data,
            transactionError: TransactionErrorDecoder::decode($data['err']),
            logs: $logs,
            unitsConsumed: $units,
        );
    }
}
