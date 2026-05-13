<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

final readonly class CompiledInstruction
{
    /**
     * @param array<int, int> $accounts Indexes into the parent message's account-keys list.
     */
    public function __construct(
        public int    $programIdIndex,
        public array  $accounts,
        public Buffer $data,
    ) {
        //
    }
}
