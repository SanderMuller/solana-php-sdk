<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use SanderMuller\SolanaPhpSdk\Util\Buffer;
use SanderMuller\SolanaPhpSdk\Util\HasSecretKey;

final class SecretKey extends Buffer implements HasSecretKey
{
    public function getSecretKey(): SecretKey
    {
        return $this;
    }

    public function getPublicKey(): PublicKey
    {
        $secret = $this->toString();
        assert($secret !== '', 'SecretKey is empty.');

        return PublicKey::from(sodium_crypto_sign_publickey_from_secretkey($secret));
    }
}
