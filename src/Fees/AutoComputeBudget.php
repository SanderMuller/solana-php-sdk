<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Fees;

use InvalidArgumentException;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\ComputeBudgetProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Transaction;
use SanderMuller\SolanaPhpSdk\Util\Signer;

/**
 * Prepends `setComputeUnitLimit` + `setComputeUnitPrice` to a transaction.
 * Limit comes from a real simulation; price from the supplied
 * {@see PriorityFeeStrategy}. Idempotent.
 *
 * @api
 */
final class AutoComputeBudget
{
    /**
     * @param array<Keypair|Signer> $signers in-process signers used to sign the simulation payload
     * @param array<int, PublicKey> $placeholderSigners extra public keys that need a reserved
     *        signature slot (e.g. {@see MessageSigner}
     *        public keys). Slots are reserved but left empty for the simulation call, which
     *        runs with `sigVerify: false`.
     * @param bool $replaceExisting when false (default), {@see InvalidArgumentException}
     *        is thrown if the transaction already carries a caller-authored
     *        `setComputeUnitLimit` / `setComputeUnitPrice` instruction. Pass true to
     *        replace them silently (the builder uses this on the second pass).
     */
    public static function apply(
        Connection $connection,
        Transaction $transaction,
        array $signers,
        PriorityFeeStrategy $strategy,
        ?ComputeBudgetEstimator $estimator = null,
        array $placeholderSigners = [],
        bool $replaceExisting = true,
    ): Transaction {
        self::stripManagedComputeBudgetInstructions($transaction, $replaceExisting);

        // When MessageSigners are present, reserve their slots via partialSign
        // (PublicKey-only — no ed25519 sign performed) so the simulation
        // serializer emits the correct number of placeholder signatures. The
        // simulator runs with `sigVerify: false`, so empty slots are accepted.
        // For local Keypair signers we ALSO pass their public keys, not the
        // signers themselves — the real signing happens later in
        // TransactionBuilder::build() against the post-injection message,
        // and re-signing twice would waste ~1ms per Keypair on the hot path.
        if ($placeholderSigners !== []) {
            $allPublicKeys = $placeholderSigners;
            foreach ($signers as $signer) {
                $allPublicKeys[] = $signer->getPublicKey();
            }

            $transaction->partialSign(...$allPublicKeys);
            $simulationSigners = [];
        } else {
            $simulationSigners = $signers;
        }

        $estimator ??= new ComputeBudgetEstimator();
        $units = $estimator->estimate($connection, $transaction, $simulationSigners);

        $writable = self::collectWritableAccounts($transaction);
        $price = $strategy->microLamportsPerCu($connection, $writable);

        $head = [
            ComputeBudgetProgram::setComputeUnitLimit($units),
            ComputeBudgetProgram::setComputeUnitPrice($price),
        ];

        $transaction->instructions = [...$head, ...$transaction->instructions];

        return $transaction;
    }

    private static function stripManagedComputeBudgetInstructions(
        Transaction $transaction,
        bool $replaceExisting,
    ): void {
        $kept = [];
        foreach ($transaction->instructions as $ix) {
            if (! ComputeBudgetProgram::isManagedBudgetInstruction($ix)) {
                $kept[] = $ix;

                continue;
            }

            if (! $replaceExisting) {
                throw new InvalidArgumentException(
                    'Transaction already carries a setComputeUnitLimit or setComputeUnitPrice instruction. '
                    . 'Pass replaceExisting: true to overwrite caller-authored compute-budget instructions.',
                );
            }
        }

        $transaction->instructions = $kept;
    }

    /**
     * @return array<int, PublicKey>
     */
    private static function collectWritableAccounts(Transaction $transaction): array
    {
        $seen = [];
        $out = [];

        foreach ($transaction->instructions as $ix) {
            foreach ($ix->keys as $meta) {
                if (! $meta->isWritable) {
                    continue;
                }

                $key = $meta->getPublicKey()->toBase58();
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $out[] = $meta->getPublicKey();
            }
        }

        return $out;
    }
}
