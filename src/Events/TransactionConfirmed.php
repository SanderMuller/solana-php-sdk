<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Events;

use SanderMuller\SolanaPhpSdk\DataObjects\SignatureStatus;

/**
 * Fired by `SanderMuller\LaravelSolanaSdk\Queue\ConfirmTransactionJob`
 * (shipped in the `sandermuller/laravel-solana-sdk` wrapper) when a
 * signature reaches the requested commitment level. Listeners receive
 * the resolved {@see SignatureStatus} and an arbitrary context payload
 * the dispatcher attached (e.g. internal order id, user id). The
 * wrapper is an optional dependency, so this package does not import
 * the class directly.
 */
final readonly class TransactionConfirmed
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $signature,
        public SignatureStatus $status,
        public array $context = [],
    ) {}
}
