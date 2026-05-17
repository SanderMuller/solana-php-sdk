<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * Decoded instruction node inside a {@see DecodedTransaction} tree.
 *
 * `path` is the dotted slot indicating where the instruction sits in
 * the transaction:
 *   - `"2"` — top-level slot 2.
 *   - `"2.1"` — inner instruction 1 nested inside top-level slot 2.
 *   - `"2.1.0"` — even deeper CPI.
 *
 * `stackHeight` mirrors the RPC field for Solana RPC 1.17+. Left
 * `null` when the RPC payload omits it — the spec deliberately does
 * not synthesize a value from log depth because logs interleave and
 * truncate.
 *
 * `idlName` and `idlArgs` are populated when a matching
 * {@see ProgramDecoder} was registered for `programId`; null
 * otherwise. The raw `data` / `dataBase58` are always populated so
 * unidentified instructions still carry their bytes.
 *
 * @api
 */
final readonly class DecodedInstruction
{
    /**
     * @param list<DecodedAccountRef>   $accounts          positional account list, role-tagged
     * @param list<DecodedInstruction>  $innerInstructions CPI tree (`path` already populated)
     * @param array<string, mixed>|null $idlArgs           name-keyed decoded arguments, or null when no IDL applies
     */
    public function __construct(
        public int $index,
        public string $path,
        public ?int $stackHeight,
        public string $programId,
        public string $data,
        public string $dataBase58,
        public array $accounts,
        public array $innerInstructions,
        public ?string $idlName = null,
        public ?array $idlArgs = null,
    ) {}
}
