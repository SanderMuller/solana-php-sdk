<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Fees;

use InvalidArgumentException;
use SanderMuller\SolanaPhpSdk\Connection;

/**
 * Returns a hardcoded priority-fee value. Useful for tests and for
 * deployments that want the same `setComputeUnitPrice` on every
 * transaction.
 *
 * @api
 */
final readonly class FixedPriorityFee implements PriorityFeeStrategy
{
    public function __construct(public int $microLamportsPerCu)
    {
        if ($microLamportsPerCu < 0) {
            throw new InvalidArgumentException('Priority fee must be non-negative.');
        }
    }

    public function microLamportsPerCu(Connection $connection, ?array $writableAccounts = null): int
    {
        return $this->microLamportsPerCu;
    }
}
