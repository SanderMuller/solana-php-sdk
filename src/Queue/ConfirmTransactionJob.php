<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Events\TransactionConfirmed;
use SanderMuller\SolanaPhpSdk\Events\TransactionExpired;
use SanderMuller\SolanaPhpSdk\Events\TransactionExpiredReason;
use SanderMuller\SolanaPhpSdk\Exceptions\BlockhashExpiredException;
use SanderMuller\SolanaPhpSdk\Exceptions\ConfirmationTimeoutException;
use SanderMuller\SolanaPhpSdk\Exceptions\TransactionFailedOnChainException;
use SanderMuller\SolanaPhpSdk\Util\Commitment;
use Throwable;

/**
 * Confirms a transaction signature from a Laravel queue worker. Polls
 * `getSignatureStatuses` until the requested commitment lands, the
 * blockhash expires, or the timeout elapses — then fires either
 * {@see TransactionConfirmed} or {@see TransactionExpired}.
 *
 * @api
 */
final class ConfirmTransactionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $context arbitrary payload re-emitted on the lifecycle event
     */
    public function __construct(
        public readonly string $signature,
        public readonly ?int $lastValidBlockHeight = null,
        public readonly ?Commitment $commitment = null,
        public readonly int $timeoutSeconds = 120,
        public readonly int $pollIntervalMs = 1_000,
        public readonly array $context = [],
    ) {}

    public function handle(Connection $connection): void
    {
        try {
            $status = $connection->confirmTransaction(
                signature: $this->signature,
                commitment: $this->commitment,
                lastValidBlockHeight: $this->lastValidBlockHeight,
                timeoutSeconds: $this->timeoutSeconds,
                pollIntervalMs: $this->pollIntervalMs,
            );
        } catch (TransactionFailedOnChainException|BlockhashExpiredException|ConfirmationTimeoutException $e) {
            event(new TransactionExpired(
                signature: $this->signature,
                reason: $this->reasonFor($e),
                detail: $e->getMessage(),
                context: $this->context,
            ));

            return;
        }

        event(new TransactionConfirmed(
            signature: $this->signature,
            status: $status,
            context: $this->context,
        ));
    }

    private function reasonFor(Throwable $e): TransactionExpiredReason
    {
        return match (true) {
            $e instanceof TransactionFailedOnChainException => TransactionExpiredReason::FailedOnChain,
            $e instanceof BlockhashExpiredException => TransactionExpiredReason::Blockhash,
            default => TransactionExpiredReason::Timeout,
        };
    }
}
