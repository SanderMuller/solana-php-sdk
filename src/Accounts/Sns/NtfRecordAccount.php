<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Accounts;

use Exception;
use SanderMuller\SolanaPhpSdk\Borsh\Borsh;
use SanderMuller\SolanaPhpSdk\Borsh\IsBorshDeserializable;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

final class NtfRecordAccount
{
    use IsBorshDeserializable;

    private const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['tag', ['u8']],
                ['nonce', ['u8']],
                ['nameAccount', ['u8']], // len 32
                ['owner', ['u8']], // len 32
                ['nftMint', ['u8']], // len 32
            ],
        ],
    ];

    /**
     * @param array<int, int> $buffer
     */
    public static function deserialize(array $buffer): self
    {
        return Borsh::deserialize(self::SCHEMA, self::class, $buffer);
    }

    public static function retrieve(Connection $connection, PublicKey $key): self
    {
        $accountInfo = $connection->getAccountInfo($key);
        $data = $accountInfo['data'] ?? null;

        if (in_array($data, [null, '', []], true)) {
            throw new Exception('NFT record not found');
        }

        $encoded = is_array($data) ? ($data[0] ?? '') : $data;
        if (! is_string($encoded) || $encoded === '') {
            throw new Exception('NFT record data missing or not base64.');
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            throw new Exception('NFT record data is not valid base64.');
        }

        $unpacked = unpack('C*', $binary);
        /** @var array<int, int> $uint8Array */
        $uint8Array = $unpacked === false ? [] : array_values($unpacked);

        return self::deserialize($uint8Array);
    }

    /**
     * @return array{0: PublicKey, 1: int}
     */
    public static function findKey(PublicKey $nameAccount, PublicKey $programId): array
    {
        return PublicKey::findProgramAddress(
            [
                Buffer::fromString('nft_record'),
                $nameAccount,
            ],
            $programId
        );
    }
}
