<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Errors;

use Stringable;

/**
 * Decoded `InstructionError` enum variant from a Solana RPC transaction
 * error. Mirrors the Rust-side
 * `solana_sdk::instruction::InstructionError` taxonomy.
 *
 * `kind` is the variant name (`Custom`, `InsufficientFunds`,
 * `BorshIoError`, ...). For variants that carry a payload, the typed
 * field is populated:
 *
 *  - `Custom(u32)` → {@see customCode}.
 *  - `BorshIoError(String)` → {@see message}.
 *
 * Variants without a payload leave the typed fields null.
 *
 * @api
 */
final readonly class InstructionError implements Stringable
{
    public function __construct(
        public string $kind,
        public ?int $customCode = null,
        public ?string $message = null,
        public mixed $raw = null,
    ) {}

    public function isCustom(): bool
    {
        return $this->kind === 'Custom';
    }

    public function __toString(): string
    {
        if ($this->kind === 'Custom') {
            return "Custom({$this->customCode})";
        }

        if ($this->kind === 'BorshIoError' && $this->message !== null) {
            return "BorshIoError({$this->message})";
        }

        return $this->kind;
    }
}
