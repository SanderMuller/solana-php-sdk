<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

final class MessageHeader
{
    public function __construct(public int $numRequiredSignature, public int $numReadonlySignedAccounts, public int $numReadonlyUnsignedAccounts) {}
}
