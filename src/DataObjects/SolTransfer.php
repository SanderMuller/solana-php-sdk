<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\DataObjects;

final readonly class SolTransfer
{
    public function __construct(
        public string   $from,
        public string   $to,
        public Lamports $amount,
    ) {
        //
    }

    /**
     * @return list<self>
     */
    public static function fromTransactionStatement(array $response): array
    {
        return collect($response['transaction']['message']['instructions'])
            ->filter(fn (array $instruction): bool => data_get($instruction, 'parsed.type') === 'transfer')
            ->map(fn (array $instruction): self => new self(
                from: data_get($instruction, 'parsed.info.source'),
                to: data_get($instruction, 'parsed.info.destination'),
                amount: new Lamports(data_get($instruction, 'parsed.info.lamports'))
            ))
            ->values()
            ->all();
    }
}
