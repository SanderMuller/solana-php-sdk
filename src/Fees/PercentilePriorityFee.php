<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Fees;

use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Util\PriorityFee;

/**
 * Samples `getRecentPrioritizationFees` and returns the requested
 * percentile (default p75). Combine with the transaction's writable
 * account list at construction time to scope the sample to slots that
 * touched those accounts.
 *
 * @api
 */
final readonly class PercentilePriorityFee implements PriorityFeeStrategy
{
    public function __construct(
        public float $percentile = PriorityFee::DEFAULT_PERCENTILE,
        public int $minMicroLamports = 0,
        public ?int $maxMicroLamports = null,
    ) {}

    public function microLamportsPerCu(Connection $connection, ?array $writableAccounts = null): int
    {
        $estimate = PriorityFee::estimate($connection, $writableAccounts, $this->percentile);

        if ($estimate < $this->minMicroLamports) {
            $estimate = $this->minMicroLamports;
        }

        if ($this->maxMicroLamports !== null && $estimate > $this->maxMicroLamports) {
            return $this->maxMicroLamports;
        }

        return $estimate;
    }
}
