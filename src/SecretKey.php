<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\HasSecretKey;

final class SecretKey extends Buffer implements HasSecretKey
{
    public function getSecretKey(): SecretKey
    {
        return $this;
    }
}
