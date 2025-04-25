<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\DataObjects;

final readonly class TransactionStatement
{
    public function __construct(
        public int $blockTime,
        public int $slot,
        public string $signature,
        public Lamports $fee,
        /** @var list<SolTransfer> */
        public array $transfers,
    ) {
        //
    }

    public static function fromResponse(array $response): self
    {
        return new self(
            blockTime: $response['blockTime'],
            slot: $response['slot'],
            signature: $response['transaction']['signatures'][0],
            fee: new Lamports($response['meta']['fee']),
            transfers: SolTransfer::fromTransactionStatement($response),
        );
    }
}
