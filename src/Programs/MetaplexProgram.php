<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

final class MetaplexProgram implements Program
{
    use IsProgram;

    private const string METAPLEX_PROGRAM_ID = 'metaqbxxUerdq28cj1RbAWkYQm3ybzjb6a8bt518x1s';

    /**
     * @return array|mixed
     */
    public function getProgramAccounts(string $pubKey): mixed
    {
        $magicOffsetNumber = 326; // 🤷‍♂️

        return $this->client->call('getProgramAccounts', [
            self::METAPLEX_PROGRAM_ID,
            [
                'encoding' => 'base64',
                'filters' => [
                    [
                        'memcmp' => [
                            'bytes' => $pubKey,
                            'offset' => $magicOffsetNumber,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
