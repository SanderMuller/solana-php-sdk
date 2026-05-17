<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * A role-tagged account reference inside a {@see DecodedInstruction}.
 * `pubkey` is the resolved base58 string (post-ALT expansion for V0
 * transactions). `idlName` is populated when the program's
 * {@see IdlRegistry} entry knew
 * the positional account name; null otherwise.
 *
 * @api
 */
final readonly class DecodedAccountRef
{
    public function __construct(
        public int $index,
        public string $pubkey,
        public bool $isSigner,
        public bool $isWritable,
        public ?string $idlName = null,
    ) {}
}
