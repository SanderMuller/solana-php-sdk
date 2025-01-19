<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

final readonly class CompiledInstruction
{
    public function __construct(
        public int    $programIdIndex,
        /**
         * array of indexes.
         *
         * @var array<int>
         */
        public array  $accounts,
        public Buffer $data,
    ) {
        //
    }
}
