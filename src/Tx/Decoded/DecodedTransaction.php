<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * Fully-decoded transaction tree. Built by
 * {@see TransactionDecoder::fromRpcResponse()} or
 * {@see TransactionDecoder::fromOutbound()} — `source` discriminates so
 * consumers can branch on construction path without sniffing null
 * fields.
 *
 * Nullability rules (do not collapse — each null carries semantic
 * weight):
 *
 * |                                 | source = Rpc | source = Outbound |
 * |---------------------------------|--------------|-------------------|
 * | `signature`                     | non-null     | null if unsigned, otherwise the first signature |
 * | `slot` / `blockTime`            | non-null     | null              |
 * | `fee`                           | non-null     | null              |
 * | `computeUnitsConsumed`          | nullable     | null              |
 * | `err`                           | null on success | null           |
 * | `logs`                          | possibly empty list | `[]`        |
 * | `meta`                          | array or null per RPC | `null`   |
 * | `loadedWritableAddresses` etc.  | populated for V0 ALTs | populated when source tx resolved its lookups |
 *
 * @api
 */
final readonly class DecodedTransaction
{
    /**
     * @param list<string>             $accountKeys              post-ALT-expansion order: static + loaded writable + loaded readonly
     * @param list<string>             $signers                  subset of $accountKeys
     * @param list<int>                $writableIndexes          indexes into $accountKeys
     * @param list<string>             $loadedWritableAddresses  ALT-loaded writable addresses (V0); [] for legacy
     * @param list<string>             $loadedReadonlyAddresses  ALT-loaded readonly addresses (V0); [] for legacy
     * @param list<DecodedInstruction> $instructions
     * @param list<DecodedLogEvent>    $logs                     always `[]` when source = Outbound
     * @param array<string, mixed>|null $meta                    raw `meta` envelope; null for Outbound or when RPC omits it
     */
    public function __construct(
        public DecodedTransactionSource $source,
        public ?string $signature,
        public ?int $slot,
        public ?int $blockTime,
        public ?int $fee,
        public ?int $computeUnitsConsumed,
        public mixed $err,
        public array $accountKeys,
        public array $signers,
        public array $writableIndexes,
        public array $loadedWritableAddresses,
        public array $loadedReadonlyAddresses,
        public array $instructions,
        public array $logs,
        public ?array $meta,
    ) {}
}
