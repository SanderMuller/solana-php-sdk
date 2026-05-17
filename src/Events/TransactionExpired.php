<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Events;

/**
 * Fired by `SanderMuller\LaravelSolanaSdk\Queue\ConfirmTransactionJob`
 * (shipped in the `sandermuller/laravel-solana-sdk` wrapper) when the
 * blockhash expires or the confirmation timeout elapses before the
 * signature lands. The wrapper is an optional dependency, so this
 * package does not import the class directly.
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
