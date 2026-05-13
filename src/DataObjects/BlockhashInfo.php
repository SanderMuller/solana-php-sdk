<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

use ArrayAccess;
use BadMethodCallException;
use SanderMuller\SolanaPhpSdk\Connection;

/**
 * Typed wrapper over the `getLatestBlockhash` RPC `value` payload.
 *
 * `lastValidBlockHeight` is the highest block height at which a transaction
 * carrying this blockhash will still be accepted by the runtime. Use it as
 * the expiry sentinel for confirmation polling — see
 * {@see Connection::confirmTransaction()}.
 *
 * Implements `ArrayAccess` so `$bh['blockhash']` legacy access keeps working.
 *
 * @implements ArrayAccess<string, string|int|null>
 */
final readonly class BlockhashInfo implements ArrayAccess
{
    public function __construct(
        public string $blockhash,
        public ?int $lastValidBlockHeight,
    ) {}

    /**
     * @param array<string, mixed> $value
     */
    public static function fromValue(array $value): self
    {
        $bh = $value['blockhash'] ?? '';
        $hash = is_string($bh) ? $bh : '';

        $lv = $value['lastValidBlockHeight'] ?? null;

        return new self(
            blockhash: $hash,
            lastValidBlockHeight: is_int($lv) ? $lv : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->blockhash === '';
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['blockhash', 'lastValidBlockHeight'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'blockhash' => $this->blockhash,
            'lastValidBlockHeight' => $this->lastValidBlockHeight,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new BadMethodCallException('BlockhashInfo is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new BadMethodCallException('BlockhashInfo is immutable.');
    }
}
