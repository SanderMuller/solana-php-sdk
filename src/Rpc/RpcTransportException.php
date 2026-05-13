<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

use SanderMuller\SolanaPhpSdk\Exceptions\SolanaPhpSdkException;
use Throwable;

/**
 * Wraps transport-level failure (HTTP non-2xx, connection refused,
 * malformed JSON). Distinct from RPC-level errors (those still arrive
 * as a successful 2xx with `error` in the body and are surfaced by
 * higher layers).
 *
 * The `transient` flag drives retry / fallback decisions — `true` for
 * 429 / 5xx / connect timeout, `false` for 4xx other than 429.
 */
final class RpcTransportException extends SolanaPhpSdkException
{
    public function __construct(
        string $message,
        public readonly bool $transient,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
