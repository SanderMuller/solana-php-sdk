<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use SanderMuller\SolanaPhpSdk\Support\PublicKeyCollection;
use SanderMuller\SolanaPhpSdk\Util\Buffer;
use SanderMuller\SolanaPhpSdk\Util\CompiledInstruction;
use SanderMuller\SolanaPhpSdk\Util\MessageHeader;

/**
 * Common interface for a serialized Solana message: legacy {@see Message}
 * or {@see MessageV0}. Used by {@see VersionedTransaction}.
 *
 * Callers that need to parse arbitrary wire-format bytes without knowing the
 * version up front should use {@see VersionedTransaction::deserialize()},
 * which dispatches to the correct concrete implementation based on the leading
 * version byte.
 */
interface VersionedMessage
{
    /**
     * Solana wire-format version. `null` means legacy (no version prefix).
     */
    public function version(): ?int;

    public function header(): MessageHeader;

    public function staticAccountKeys(): PublicKeyCollection;

    public function recentBlockhash(): string;

    /**
     * @return array<CompiledInstruction>
     */
    public function compiledInstructions(): array;

    /**
     * Wire bytes used for signing and inclusion in a transaction envelope.
     */
    public function serialize(): string;

    /**
     * Parse wire-format bytes into the concrete message type.
     *
     * @param Buffer|array<int, int>|string $buffer
     */
    public static function deserialize(Buffer|array|string $buffer): self;
}
