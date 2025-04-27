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
        $accountKeys = $response['transaction']['message']['accountKeys'] ?? [];
        $instructions = $response['transaction']['message']['instructions'] ?? [];

        // Find Memo Program ID index
        $memoProgramId = 'MemoSq4gqABAXKb96qnH8TysNcWxMyWCqXgDLGmfcHr';

        $memos = [];

        foreach ($instructions as $instruction) {
            $programIdIndex = $instruction['programIdIndex'] ?? null;

            if ($programIdIndex === null || ! isset($accountKeys[$programIdIndex])) {
                continue;
            }

            if ($accountKeys[$programIdIndex] === $memoProgramId) {
                $encodedData = $instruction['data'] ?? '';

                if ($encodedData !== '') {
                    $decoded = base64_decode((string) $encodedData, true);
                    if ($decoded !== false) {
                        $memos[] = new self($decoded);
                    }
                }
            }
        }

        return $memos;
    }
}
