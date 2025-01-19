<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\PublicKey;

final readonly class Signer implements HasPublicKey, HasSecretKey
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
