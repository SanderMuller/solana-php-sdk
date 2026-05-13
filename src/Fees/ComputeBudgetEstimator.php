<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Fees;

use InvalidArgumentException;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Transaction;
use SanderMuller\SolanaPhpSdk\Util\Signer;

/**
 * Reads `unitsConsumed` from a `simulateTransaction` result, scales by a
 * safety buffer, and clamps to the Solana 1.4M CU cap.
 *
 * @api
 */
final readonly class ComputeBudgetEstimator
{
    public const int MAX_COMPUTE_UNITS = 1_400_000;

    public const int MIN_COMPUTE_UNITS = 1_000;

    public function __construct(
        public float $bufferRatio = 1.2,
        public int $floor = self::MIN_COMPUTE_UNITS,
    ) {
        if ($bufferRatio <= 0.0) {
            throw new InvalidArgumentException('Buffer ratio must be positive.');
        }

        if ($floor < 0) {
            throw new InvalidArgumentException('Floor must be non-negative.');
        }
    }

    /**
     * Simulate the transaction and return the scaled compute-unit budget.
     *
     * @param array<Keypair|Signer> $signers
     */
    public function estimate(Connection $connection, Transaction $transaction, array $signers = []): int
    {
        $result = $connection->simulateTransaction(
            $transaction,
            $signers,
            ['sigVerify' => false, 'replaceRecentBlockhash' => true],
        );

        $value = is_array($result['value'] ?? null) ? $result['value'] : $result;
        $units = isset($value['unitsConsumed']) && is_int($value['unitsConsumed'])
            ? $value['unitsConsumed']
            : null;

        if ($units === null) {
            return self::MAX_COMPUTE_UNITS;
        }

        $scaled = (int) ceil($units * $this->bufferRatio);

        return max($this->floor, min(self::MAX_COMPUTE_UNITS, $scaled));
    }
}
