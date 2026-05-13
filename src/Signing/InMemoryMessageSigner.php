<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Signing;

use SanderMuller\SolanaPhpSdk\Contracts\MessageSigner;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\PublicKey;

/**
 * In-memory {@see MessageSigner} backed by a local {@see Keypair}.
 *
 * Convenience adapter for the common case where the secret bytes live in
 * the same process. Hosts running on a KMS / HSM should implement
 * {@see MessageSigner} directly against their service rather than
 * exporting bytes into this wrapper.
 */
final readonly class InMemoryMessageSigner implements MessageSigner
{
    public function __construct(private Keypair $keypair) {}

    public static function fromKeypair(Keypair $keypair): self
    {
        return new self($keypair);
    }

    public function getPublicKey(): PublicKey
    {
        return $this->keypair->getPublicKey();
    }

    public function signMessage(string $message): string
    {
        return $this->keypair->sign($message);
    }
}
