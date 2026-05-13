<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Events;

use SanderMuller\SolanaPhpSdk\Queue\ConfirmTransactionJob;

/**
 * Fired by {@see ConfirmTransactionJob} when the blockhash expires or
 * the confirmation timeout elapses before the signature lands.
 */
final readonly class TransactionExpired
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $signature,
        public TransactionExpiredReason $reason,
        public ?string $detail = null,
        public array $context = [],
    ) {}
}
