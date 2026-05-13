<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\SecretKey;

interface HasSecretKey
{
    public function getSecretKey(): SecretKey;
}
