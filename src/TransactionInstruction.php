<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use SanderMuller\SolanaPhpSdk\Util\AccountMeta;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

final class TransactionInstruction
{
    public Buffer $data;

    /**
     * @param array<AccountMeta>      $keys
     * @param Buffer|string|array<int, int>|null $data
     */
    public function __construct(
        public PublicKey               $programId,
        public array                   $keys,
        Buffer|string|array|null       $data = null,
    ) {
        $this->data = Buffer::from($data);
    }
}
