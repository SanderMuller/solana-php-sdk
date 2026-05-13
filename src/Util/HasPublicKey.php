<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\PublicKey;

interface HasPublicKey
{
    public function getPublicKey(): PublicKey;
}
