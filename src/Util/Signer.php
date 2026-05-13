<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\SecretKey;

/**
 * Holder for an externally-managed signer.
 *
 * `getSecretKey()` returns a raw {@see Buffer} (rather than a typed
 * {@see SecretKey}) because callers that produce
 * `Signer` instances often hold secret material as bytes from outside the SDK
 * (HSM exports, wallet adapters). The class therefore does **not** implement
 * {@see HasSecretKey} — `HasSecretKey::getSecretKey(): SecretKey` is the
 * SDK-internal contract used by `Keypair`.
 */
final readonly class Signer implements HasPublicKey
{
    public function __construct(private PublicKey $publicKey, private Buffer $secretKey) {}

    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }

    public function getSecretKey(): Buffer
    {
        return $this->secretKey;
    }
}
