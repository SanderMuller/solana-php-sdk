<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

final readonly class SolMemo
{
    public function __construct(
        public string $memo,
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
            if (! is_array($instruction) || data_get($instruction, 'program') !== 'spl-memo') {
                continue;
            }

            $parsed = data_get($instruction, 'parsed');
            $out[] = new self(is_string($parsed) ? $parsed : '');
        }

        return $out;
    }
}
