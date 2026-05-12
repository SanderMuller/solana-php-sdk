<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

/**
 * Loaded contents of an on-chain Address Lookup Table.
 *
 * Holds the table's account key together with the resolved list of addresses
 * stored in the table. Used when compiling a v0 message that references the
 * table — the compiler picks indexes into {@see $addresses} and emits them
 * inside a {@see Util\MessageAddressTableLookup}.
 */
final readonly class AddressLookupTableAccount
{
    public function __construct(
        public PublicKey $key,
        /** @var array<PublicKey> Addresses stored in the table, in index order. */
        public array $addresses,
    ) {}
}
