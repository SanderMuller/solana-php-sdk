<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * Pluggable contract for decoding a specific Solana program's
 * instruction bytes into a human-friendly `{name, args}` shape.
 *
 * v1 ships only `Anchor\AnchorProgramDecoder` (the 8-byte discriminator
 * path). Non-Anchor programs (System / SPL Token / Memo / Token-2022 /
 * ComputeBudget) ship their own decoders in a future release.
 *
 * Return null when the data shape is not recognised — the caller falls
 * back to raw bytes only and does not abort the surrounding decode.
 *
 * @api
 */
interface ProgramDecoder
{
    public function programId(): string;

    /**
     * @param list<DecodedAccountRef> $accounts positional account list already populated
     *        with on-chain pubkeys (post-ALT expansion). Implementations may consult this
     *        to attach IDL-resolved account names.
     *
     * @return array{name: string, args: array<string, mixed>, accountNames?: list<?string>}|null
     */
    public function decode(string $instructionData, array $accounts): ?array;
}
