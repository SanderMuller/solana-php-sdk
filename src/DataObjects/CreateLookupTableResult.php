<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

use ArrayAccess;
use BadMethodCallException;
use SanderMuller\SolanaPhpSdk\Programs\AddressLookupTableProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;

/**
 * Returned by {@see AddressLookupTableProgram::createLookupTable()}.
 *
 * Holds the `CreateLookupTable` instruction plus the canonical lookup-table
 * PDA + bump seed derived for the supplied authority/slot. Implements
 * `ArrayAccess` so legacy `$result['instruction']` call sites keep working.
 *
 * @implements ArrayAccess<string, TransactionInstruction|PublicKey|int>
 */
final readonly class CreateLookupTableResult implements ArrayAccess
{
    public function __construct(
        public TransactionInstruction $instruction,
        public PublicKey $lookupTableAddress,
        public int $bumpSeed,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['instruction', 'lookupTableAddress', 'bumpSeed'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'instruction' => $this->instruction,
            'lookupTableAddress' => $this->lookupTableAddress,
            'bumpSeed' => $this->bumpSeed,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new BadMethodCallException('CreateLookupTableResult is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new BadMethodCallException('CreateLookupTableResult is immutable.');
    }
}
