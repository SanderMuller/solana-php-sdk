<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

/**
 * Composite trait for objects that round-trip through Borsh. The dynamic
 * `$fields` storage lives on {@see IsBorshDeserializable}; this trait simply
 * pulls in both halves.
 */
trait IsBorshObject
{
    use IsBorshDeserializable;
    use IsBorshSerializable;
}
