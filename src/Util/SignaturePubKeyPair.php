<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\PublicKey;

final class SignaturePubKeyPair implements HasPublicKey
{
    public function __construct(
        private readonly PublicKey $publicKey,
        public ?string             $signature = null,
    ) {
        //
    }

    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }
}
