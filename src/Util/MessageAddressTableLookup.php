<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\PublicKey;

final readonly class MessageAddressTableLookup
{
    public function __construct(
        public PublicKey $accountKey,
        /** @var array<int> */
        public array $writableIndexes,
        /** @var array<int> */
        public array $readonlyIndexes,
    ) {}
}
