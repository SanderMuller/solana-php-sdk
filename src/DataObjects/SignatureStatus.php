<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

use SanderMuller\SolanaPhpSdk\Util\Commitment;

/**
 * The status of a signature on chain. Mirrors the value array returned by
 * `getSignatureStatuses`.
 */
final readonly class SignatureStatus
{
    public function __construct(
        public string  $signature,
        public int     $slot,
        public ?int    $confirmations,
        public ?string $confirmationStatus,
        /** RPC `err` payload — null when the transaction succeeded. */
        public mixed   $err,
    ) {}

    /**
     * @param array{slot?: int, confirmations?: int|null, confirmationStatus?: string|null, err?: mixed} $value
     */
    public static function fromValue(string $signature, array $value): self
    {
        return new self(
            signature: $signature,
            slot: $value['slot'] ?? 0,
            confirmations: $value['confirmations'] ?? null,
            confirmationStatus: $value['confirmationStatus'] ?? null,
            err: $value['err'] ?? null,
        );
    }

    public function isFinalized(): bool
    {
        return $this->confirmationStatus === Commitment::FINALIZED;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmationStatus === Commitment::CONFIRMED
            || $this->isFinalized();
    }

    public function isProcessed(): bool
    {
        return $this->confirmationStatus === Commitment::PROCESSED
            || $this->isConfirmed();
    }

    public function reachedAtLeast(Commitment $commitment): bool
    {
        return match ($commitment->commitmentLevel) {
            Commitment::FINALIZED => $this->isFinalized(),
            Commitment::CONFIRMED => $this->isConfirmed(),
            Commitment::PROCESSED => $this->isProcessed(),
            default => false,
        };
    }
}
