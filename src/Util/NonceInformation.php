<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

use SanderMuller\SolanaPhpSdk\TransactionInstruction;

final class NonceInformation
{
    public function __construct(
        public string                 $nonce,
        public TransactionInstruction $nonceInstruction,
    ) {
        //
    }
}
