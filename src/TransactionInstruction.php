<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;

final class TransactionInstruction
{
    public Buffer $data;

    public function __construct(
        public PublicKey   $programId,
        /**
         * @var AccountMeta[]
         */
        public array       $keys,
        Buffer|string|null|array $data = null,
    ) {
        $this->data = Buffer::from($data);
    }
}
