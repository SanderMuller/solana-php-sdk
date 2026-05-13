<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Fees;

use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\PublicKey;

/**
 * Decides the `setComputeUnitPrice` micro-lamports-per-CU value for a
 * specific outbound transaction. Implementations may consult the
 * network's recent prioritization-fee distribution, query a
 * provider-specific API (Helius / Triton), or return a static value.
 *
 * `microLamportsPerCu` must be non-negative. Zero is a valid response
 * when the network is idle.
 *
 * @api
 */
interface PriorityFeeStrategy
{
    /**
     * @param array<int, string|PublicKey>|null $writableAccounts the
     *        prospective transaction's writable account list. Strategies
     *        that consult `getRecentPrioritizationFees` pass this through
     *        so the sample tightens to slots that touched those accounts.
     */
    public function microLamportsPerCu(Connection $connection, ?array $writableAccounts = null): int;
}
