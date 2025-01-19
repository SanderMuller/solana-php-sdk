<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\SecretKey;

interface HasSecretKey
{
    public function getSecretKey(): SecretKey;
}
