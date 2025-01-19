<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\PublicKey;

final class AccountMeta implements HasPublicKey
{
    public function __construct(private readonly PublicKey $publicKey, public bool $isSigner, public bool $isWritable) {}

    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }
}
