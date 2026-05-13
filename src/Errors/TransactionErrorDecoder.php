<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Errors;

/**
 * Decodes the `err` field returned by the Solana RPC
 * `sendTransaction` / `simulateTransaction` error data block into a
 * structured {@see TransactionError} / {@see InstructionError} tree.
 *
 * The `err` field is the JSON-serialised Rust enum
 * `solana_sdk::transaction::TransactionError`, which has two shapes:
 *
 *  - String for unit variants: `"BlockhashNotFound"`,
 *    `"AlreadyProcessed"`, `"AccountNotFound"`, ...
 *  - Object for variants with payload: `{"InstructionError": [0, ...]}`,
 *    `{"DuplicateInstruction": 2}`, `{"InsufficientFundsForRent": {"account_index": 1}}`.
 *
 * @api
 */
final class TransactionErrorDecoder
{
    public static function decode(mixed $err): TransactionError
    {
        if (is_string($err)) {
            return new TransactionError(kind: $err, raw: $err);
        }

        if (! is_array($err) || $err === []) {
            return new TransactionError(kind: 'Unknown', raw: $err);
        }

        // Single-key associative array — that key is the variant name.
        $kind = array_key_first($err);
        if (! is_string($kind)) {
            return new TransactionError(kind: 'Unknown', raw: $err);
        }

        $payload = $err[$kind];

        return match ($kind) {
            'InstructionError' => self::decodeInstructionVariant($payload, $err),
            'DuplicateInstruction' => new TransactionError(
                kind: $kind,
                instructionIndex: self::asInt($payload),
                raw: $err,
            ),
            'InsufficientFundsForRent',
            'ProgramExecutionTemporarilyRestricted' => new TransactionError(
                kind: $kind,
                accountIndex: self::asAccountIndex($payload),
                raw: $err,
            ),
            default => new TransactionError(kind: $kind, raw: $err),
        };
    }

    /**
     * @param array<mixed>|string $raw
     */
    private static function decodeInstructionVariant(mixed $payload, array|string $raw): TransactionError
    {
        if (! is_array($payload) || count($payload) < 2) {
            return new TransactionError(kind: 'InstructionError', raw: $raw);
        }

        $index = self::asInt($payload[0]);
        $inner = self::decodeInstructionError($payload[1]);

        return new TransactionError(
            kind: 'InstructionError',
            instructionIndex: $index,
            instructionError: $inner,
            raw: $raw,
        );
    }

    public static function decodeInstructionError(mixed $err): InstructionError
    {
        if (is_string($err)) {
            return new InstructionError(kind: $err, raw: $err);
        }

        if (! is_array($err) || $err === []) {
            return new InstructionError(kind: 'Unknown', raw: $err);
        }

        $kind = array_key_first($err);
        if (! is_string($kind)) {
            return new InstructionError(kind: 'Unknown', raw: $err);
        }

        $payload = $err[$kind];

        return match ($kind) {
            'Custom' => new InstructionError(
                kind: 'Custom',
                customCode: self::asInt($payload),
                raw: $err,
            ),
            'BorshIoError' => new InstructionError(
                kind: 'BorshIoError',
                message: is_string($payload) ? $payload : null,
                raw: $err,
            ),
            default => new InstructionError(kind: $kind, raw: $err),
        };
    }

    private static function asInt(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }

    private static function asAccountIndex(mixed $payload): ?int
    {
        if (is_int($payload)) {
            return $payload;
        }

        if (is_array($payload) && isset($payload['account_index'])) {
            return self::asInt($payload['account_index']);
        }

        return null;
    }
}
