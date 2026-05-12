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
     * @param array<string, mixed> $response
     * @return list<self>
     */
    public static function fromTransactionStatement(array $response): array
    {
        $instructions = data_get($response, 'transaction.message.instructions');
        if (! is_array($instructions)) {
            return [];
        }

        $out = [];
        foreach ($instructions as $instruction) {
            if (! is_array($instruction) || data_get($instruction, 'parsed.type') !== 'transfer') {
                continue;
            }

            $source = data_get($instruction, 'parsed.info.source');
            $destination = data_get($instruction, 'parsed.info.destination');
            $lamports = data_get($instruction, 'parsed.info.lamports');

            $out[] = new self(
                from: is_string($source) ? $source : '',
                to: is_string($destination) ? $destination : '',
                amount: new Lamports(is_numeric($lamports) ? (int) $lamports : 0),
            );
        }

        return $out;
    }
}
