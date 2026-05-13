<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use SanderMuller\SolanaPhpSdk\Contracts\MessageSigner;
use SanderMuller\SolanaPhpSdk\DataObjects\BlockhashInfo;
use SanderMuller\SolanaPhpSdk\Exceptions\UnsanitizedTransactionException;
use SanderMuller\SolanaPhpSdk\Fees\AutoComputeBudget;
use SanderMuller\SolanaPhpSdk\Fees\ComputeBudgetEstimator;
use SanderMuller\SolanaPhpSdk\Fees\PriorityFeeStrategy;
use SanderMuller\SolanaPhpSdk\Util\Signer;

/**
 * Sanitize-safe builder for {@see Transaction}.
 *
 * The Solana runtime's sanitize pass is strict — duplicate accounts whose
 * writable/signer flags don't agree, missing fee payer in the signer set,
 * or instruction accounts marked `isSigner` with no corresponding keypair
 * all produce the same opaque "Transaction failed to sanitize accounts
 * offsets correctly" RPC error after the round-trip. This builder catches
 * those locally and surfaces every detected reason at once via
 * {@see UnsanitizedTransactionException}.
 *
 * `build()` is the final commit step; until then the builder is mutable
 * and chainable. `build()` re-validates each time, so the same builder
 * can produce multiple sanity-checked transactions.
 *
 * Example:
 *
 * ```php
 * $tx = TransactionBuilder::new()
 *     ->feePayer($payer->getPublicKey())
 *     ->recentBlockhash($blockhashInfo)
 *     ->addInstruction(SystemProgram::transfer($payer->getPublicKey(), $to, 1))
 *     ->addSigner($payer)
 *     ->build();
 * ```
 */
final class TransactionBuilder
{
    private ?PublicKey $feePayer = null;

    private string|PublicKey|null $recentBlockhash = null;

    /** @var array<TransactionInstruction> */
    private array $instructions = [];

    /** @var array<Keypair|Signer> */
    private array $signers = [];

    /** @var array<MessageSigner> */
    private array $messageSigners = [];

    private ?Connection $autoBudgetConnection = null;

    private ?PriorityFeeStrategy $autoBudgetStrategy = null;

    private ?ComputeBudgetEstimator $autoBudgetEstimator = null;

    public static function new(): self
    {
        return new self();
    }

    public function feePayer(PublicKey $payer): self
    {
        $this->feePayer = $payer;

        return $this;
    }

    /**
     * Accepts a raw blockhash string or a {@see BlockhashInfo} DTO (whose
     * `lastValidBlockHeight` is honoured by `Connection::confirmTransaction`).
     */
    public function recentBlockhash(string|PublicKey|BlockhashInfo $blockhash): self
    {
        $this->recentBlockhash = $blockhash instanceof BlockhashInfo
            ? $blockhash->blockhash
            : $blockhash;

        return $this;
    }

    public function addInstruction(TransactionInstruction $instruction): self
    {
        $this->instructions[] = $instruction;

        return $this;
    }

    public function addInstructions(TransactionInstruction ...$instructions): self
    {
        foreach ($instructions as $instruction) {
            $this->instructions[] = $instruction;
        }

        return $this;
    }

    public function addSigner(Keypair|Signer $signer): self
    {
        $this->signers[] = $signer;

        return $this;
    }

    public function addSigners(Keypair|Signer ...$signers): self
    {
        foreach ($signers as $signer) {
            $this->signers[] = $signer;
        }

        return $this;
    }

    /**
     * Register a remote / external signer (KMS, HSM, hardware wallet, …).
     * The builder will compile + serialize the message, hand the bytes to
     * `signMessage()`, and attach the resulting signature on the
     * corresponding fee-payer / signer slot. Use {@see addSigner()} when
     * the secret material lives in-process.
     */
    public function addMessageSigner(MessageSigner $signer): self
    {
        $this->messageSigners[] = $signer;

        return $this;
    }

    /**
     * Opt the builder into automatic ComputeBudget injection: on
     * {@see build()}, the assembled transaction is simulated through the
     * connection, `setComputeUnitLimit` is derived from `unitsConsumed`
     * (scaled by the estimator's buffer), and `setComputeUnitPrice` is
     * derived from the {@see PriorityFeeStrategy}. Prepended to the head
     * of the instruction list before signing.
     */
    public function withAutoComputeBudget(
        Connection $connection,
        PriorityFeeStrategy $strategy,
        ?ComputeBudgetEstimator $estimator = null,
    ): self {
        $this->autoBudgetConnection = $connection;
        $this->autoBudgetStrategy = $strategy;
        $this->autoBudgetEstimator = $estimator;

        return $this;
    }

    /**
     * @throws UnsanitizedTransactionException when the builder is incomplete
     *         or the assembled message would fail runtime sanitize.
     */
    public function build(): Transaction
    {
        $reasons = $this->validate();
        if ($reasons !== []) {
            throw new UnsanitizedTransactionException($reasons);
        }

        $tx = new Transaction(
            recentBlockhash: $this->recentBlockhash,
            feePayer: $this->feePayer,
        );
        $tx->addInstructions(...$this->instructions);

        if ($this->autoBudgetConnection instanceof Connection && $this->autoBudgetStrategy instanceof PriorityFeeStrategy) {
            $placeholderSigners = array_values(array_map(
                static fn (MessageSigner $s): PublicKey => $s->getPublicKey(),
                $this->messageSigners,
            ));

            AutoComputeBudget::apply(
                $this->autoBudgetConnection,
                $tx,
                $this->signers,
                $this->autoBudgetStrategy,
                $this->autoBudgetEstimator,
                $placeholderSigners,
            );
        }

        // Reserve a signature slot for every MessageSigner public key by
        // passing them as bare PublicKeys through partialSign() — the
        // existing signing pipeline knows to skip the in-process sodium
        // path for pure-PublicKey entries and leaves the slot empty for us.
        $partialSigners = [
            ...$this->signers,
            ...array_map(static fn (MessageSigner $s): PublicKey => $s->getPublicKey(), $this->messageSigners),
        ];

        if ($partialSigners !== []) {
            $tx->partialSign(...$partialSigners);
        }

        if ($this->messageSigners !== []) {
            $signData = $tx->serializeMessage();
            foreach ($this->messageSigners as $messageSigner) {
                $tx->addSignature($messageSigner->getPublicKey(), $messageSigner->signMessage($signData));
            }
        }

        return $tx;
    }

    /**
     * @return list<string>
     */
    private function validate(): array
    {
        $reasons = [];

        if (! $this->feePayer instanceof PublicKey) {
            $reasons[] = 'feePayer() is required.';
        }

        if ($this->recentBlockhash === null || $this->recentBlockhash === '') {
            $reasons[] = 'recentBlockhash() is required.';
        }

        if ($this->instructions === []) {
            $reasons[] = 'At least one instruction is required.';
        }

        $signerKeys = [];
        foreach ($this->signers as $signer) {
            $signerKeys[$signer->getPublicKey()->toBase58()] = true;
        }

        foreach ($this->messageSigners as $messageSigner) {
            $signerKeys[$messageSigner->getPublicKey()->toBase58()] = true;
        }

        if ($this->feePayer instanceof PublicKey && ! isset($signerKeys[$this->feePayer->toBase58()])) {
            $reasons[] = sprintf(
                'feePayer %s is not present in the signer set.',
                $this->feePayer->toBase58(),
            );
        }

        foreach ($this->validateInstructionAccounts($signerKeys) as $reason) {
            $reasons[] = $reason;
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Walk each instruction's account list, detecting flag conflicts on
     * duplicates and signer flags without a matching keypair.
     *
     * @param array<string, true> $signerKeys
     * @return list<string>
     */
    private function validateInstructionAccounts(array $signerKeys): array
    {
        $reasons = [];

        /** @var array<string, array{isSigner: bool, isWritable: bool}> $accountState */
        $accountState = [];

        foreach ($this->instructions as $i => $instruction) {
            foreach ($instruction->keys as $j => $meta) {
                $key = $meta->getPublicKey()->toBase58();

                if (isset($accountState[$key])) {
                    if ($accountState[$key]['isSigner'] !== $meta->isSigner) {
                        $reasons[] = sprintf(
                            'Account %s appears with conflicting isSigner flags (instruction[%d].keys[%d]).',
                            $key,
                            $i,
                            $j,
                        );
                    }

                    // Writable conflicts are merged by the runtime — most-permissive wins.
                    if ($accountState[$key]['isWritable'] !== $meta->isWritable) {
                        $accountState[$key]['isWritable'] = true;
                    }
                } else {
                    $accountState[$key] = [
                        'isSigner' => $meta->isSigner,
                        'isWritable' => $meta->isWritable,
                    ];
                }

                if ($meta->isSigner && ! isset($signerKeys[$key]) && $key !== ($this->feePayer?->toBase58() ?? '')) {
                    $reasons[] = sprintf(
                        'Account %s is marked isSigner in instruction[%d] but no matching signer was added.',
                        $key,
                        $i,
                    );
                }
            }
        }

        return $reasons;
    }
}
