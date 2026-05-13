<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Programs\ComputeBudgetProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;

/**
 * Priority fee estimator over {@see Connection::getRecentPrioritizationFees}.
 *
 * Solana validators expose recent prioritization fees as a list of
 * `{slot, prioritizationFee}` pairs (micro-lamports per compute unit). The
 * RPC method itself returns no aggregation — every Solana SDK rolls its own
 * heuristic. This helper takes the empirical distribution and returns a
 * percentile so callers can pick e.g. p75 ("beat 75% of recent traffic") or
 * p95 ("priority-leaning"). Returning zero is intentional when the network
 * is idle — no fee is necessary in that regime.
 */
final class PriorityFee
{
    public const float DEFAULT_PERCENTILE = 0.75;

    /**
     * Sample the network's recent prioritization-fee distribution and return
     * the requested percentile in micro-lamports per compute unit.
     *
     * @param array<int, string|PublicKey>|null $writableAccounts addresses the
     *        prospective transaction will *write*; the RPC filters the sample
     *        to slots that touched them, giving a tighter estimate. Pass null
     *        for a global sample.
     *
     * @throws InputValidationException
     */
    public static function estimate(
        Connection $connection,
        ?array $writableAccounts = null,
        float $percentile = self::DEFAULT_PERCENTILE,
    ): int {
        if ($percentile < 0.0 || $percentile > 1.0) {
            throw new InputValidationException('Percentile must be between 0 and 1.');
        }

        $samples = $connection->getRecentPrioritizationFees($writableAccounts);

        $fees = array_values(array_map(
            static fn (array $entry): int => $entry['prioritizationFee'],
            $samples,
        ));

        return self::percentile($fees, $percentile);
    }

    /**
     * Build the ComputeBudget instruction pair (`setComputeUnitLimit` +
     * `setComputeUnitPrice`) for the requested CU budget and the sampled
     * priority fee. Drop these at the head of the instruction list.
     *
     * @param array<int, string|PublicKey>|null $writableAccounts
     *
     * @return array{TransactionInstruction, TransactionInstruction}
     */
    public static function buildInstructions(
        Connection $connection,
        int $computeUnitLimit,
        ?array $writableAccounts = null,
        float $percentile = self::DEFAULT_PERCENTILE,
    ): array {
        $microLamports = self::estimate($connection, $writableAccounts, $percentile);

        return [
            ComputeBudgetProgram::setComputeUnitLimit($computeUnitLimit),
            ComputeBudgetProgram::setComputeUnitPrice($microLamports),
        ];
    }

    /**
     * Nearest-rank percentile. Returns 0 for an empty sample.
     *
     * @param array<int, int> $sorted
     */
    private static function percentile(array $sorted, float $percentile): int
    {
        if ($sorted === []) {
            return 0;
        }

        sort($sorted);

        $rank = (int) ceil($percentile * count($sorted)) - 1;

        return $sorted[max(0, $rank)];
    }
}
