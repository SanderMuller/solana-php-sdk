<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\PublicKey;

interface HasPublicKey
{
    public function getPublicKey(): PublicKey;
}
