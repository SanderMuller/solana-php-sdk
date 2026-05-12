<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\DataObjects;

final readonly class TransactionStatement
{
    public function __construct(
        public int      $blockTime,
        public int      $slot,
        public string   $signature,
        public Lamports $fee,
        /** @var list<SolTransfer> */
        public array    $transfers,
        /** @var list<SolMemo> */
        public array    $memos,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromResponse(array $response): self
    {
        $blockTime = data_get($response, 'blockTime', 0);
        $slot = data_get($response, 'slot', 0);
        $signature = data_get($response, 'transaction.signatures.0', '');
        $fee = data_get($response, 'meta.fee', 0);

        return new self(
            blockTime: is_numeric($blockTime) ? (int) $blockTime : 0,
            slot: is_numeric($slot) ? (int) $slot : 0,
            signature: is_string($signature) ? $signature : '',
            fee: new Lamports(is_numeric($fee) ? (int) $fee : 0),
            transfers: SolTransfer::fromTransactionStatement($response),
            memos: SolMemo::fromTransactionStatement($response),
        );
    }
}
