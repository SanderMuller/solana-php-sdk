<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\TransactionInstruction;

final class NonceInformation
{
    public function __construct(
        public string                 $nonce,
        public TransactionInstruction $nonceInstruction,
    ) {
        //
    }
}
