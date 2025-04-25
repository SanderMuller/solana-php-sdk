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
        $transfers = [];

        $instructions = $response['transaction']['message']['instructions'] ?? [];
        $accountKeys = $response['transaction']['message']['accountKeys'] ?? [];
        $programIdKeys = $accountKeys;

        foreach ($instructions as $instruction) {
            $programIdIndex = $instruction['programIdIndex'] ?? null;
            if ($programIdIndex === null) {
                continue;
            }

            $programId = $programIdKeys[$programIdIndex] ?? null;
            if ($programId !== '11111111111111111111111111111111') {
                continue; // not a SystemProgram transfer
            }

            $accountIndexes = $instruction['accounts'] ?? [];
            if (count($accountIndexes) < 2) {
                continue; // need at least from + to
            }

            $from = $accountKeys[$accountIndexes[0]];
            $to = $accountKeys[$accountIndexes[1]];

            $encodedData = $instruction['data'] ?? '';
            $decoded = base64_decode($encodedData, true);

            if (! $decoded || strlen($decoded) !== 12) {
                continue; // invalid format
            }

            $instructionId = substr($decoded, 0, 4);
            if ($instructionId !== "\x02\x00\x00\x00") {
                continue; // not a transfer instruction
            }

            $amount = unpack('P', substr($decoded, 4, 8))[1]; // little-endian uint64

            $transfers[] = new self(
                from: $from,
                to: $to,
                amount: new Lamports($amount),
            );
        }

        return $transfers;
    }
}
