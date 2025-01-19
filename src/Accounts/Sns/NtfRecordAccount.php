<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Accounts;

use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshDeserializable;
use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Util\Buffer;

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

    public static function deserialize(array $buffer): self
    {
        return Borsh::deserialize(self::SCHEMA, self::class, $buffer);
    }

    public static function retrieve(Connection $connection, PublicKey $key): self
    {
        $accountInfo = $connection->getAccountInfo($key);
        if (! $accountInfo || ! $accountInfo['data']) {
            throw new \Exception('NFT record not found');
        }

        $base64String = base64_decode((string) $accountInfo['data']);
        $uint8Array = array_values(unpack('C*', $base64String));

        return self::deserialize($uint8Array);
    }

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
