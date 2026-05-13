<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Errors;

use Stringable;

/**
 * Decoded `TransactionError` from a Solana RPC `sendTransaction` /
 * `simulateTransaction` failure. Mirrors the Rust-side
 * `solana_sdk::transaction::TransactionError` taxonomy.
 *
 * `kind` is the variant name (`InstructionError`, `BlockhashNotFound`,
 * `AlreadyProcessed`, `DuplicateInstruction`, `InsufficientFundsForRent`,
 * ...). Variants that carry typed payload populate the matching field:
 *
 *  - `InstructionError(u8, InstructionError)` → {@see instructionIndex} +
 *    {@see instructionError}.
 *  - `DuplicateInstruction(u8)` → {@see instructionIndex}.
 *  - `InsufficientFundsForRent { account_index: u8 }` /
 *    `ProgramExecutionTemporarilyRestricted { account_index: u8 }` →
 *    {@see accountIndex}.
 *
 * All variants preserve the verbatim Rust enum JSON in {@see raw} so
 * callers that want to switch on something unanticipated can.
 *
 * @api
 */
final readonly class TransactionError implements Stringable
{
    public function __construct(
        public string $kind,
        public ?int $instructionIndex = null,
        public ?InstructionError $instructionError = null,
        public ?int $accountIndex = null,
        public mixed $raw = null,
    ) {}

    public function isInstructionError(): bool
    {
        return $this->kind === 'InstructionError';
    }

    public function isBlockhashNotFound(): bool
    {
        return $this->kind === 'BlockhashNotFound';
    }

    public function isAlreadyProcessed(): bool
    {
        return $this->kind === 'AlreadyProcessed';
    }

    public function isCustomError(): bool
    {
        return $this->instructionError?->isCustom() === true;
    }

    public function customCode(): ?int
    {
        return $this->instructionError?->customCode;
    }

    public function __toString(): string
    {
        if ($this->kind === 'InstructionError') {
            return sprintf(
                'InstructionError(%d, %s)',
                $this->instructionIndex ?? -1,
                (string) ($this->instructionError ?? new InstructionError('?')),
            );
        }

        if ($this->kind === 'DuplicateInstruction') {
            return "DuplicateInstruction({$this->instructionIndex})";
        }

        if ($this->accountIndex !== null) {
            return "{$this->kind}{account_index: {$this->accountIndex}}";
        }

        return $this->kind;
    }
}
