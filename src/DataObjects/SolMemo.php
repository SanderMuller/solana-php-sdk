<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\DataObjects;

final readonly class SolMemo
{
    public function __construct(
        public string $memo,
    ) {
        //
    }

    /**
     * @return list<self>
     */
    public static function fromTransactionStatement(array $response): array
    {
        return collect($response['transaction']['message']['instructions'])
            ->filter(fn (array $instruction): bool => data_get($instruction, 'program') === 'spl-memo')
            ->map(fn (array $instruction): self => new self(data_get($instruction, 'parsed')))
            ->values()
            ->all();
    }
}
