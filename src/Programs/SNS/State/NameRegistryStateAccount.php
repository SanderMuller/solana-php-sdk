<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs\SNS\State;

use SanderMuller\SolanaPhpSdk\Borsh\Borsh;
use SanderMuller\SolanaPhpSdk\Borsh\BorshSerializable;
use SanderMuller\SolanaPhpSdk\Borsh\IsBorshObject;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use SanderMuller\SolanaPhpSdk\Exceptions\SNSError;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

/**
 * @property PublicKey $parentName
 * @property PublicKey $owner
 * @property PublicKey $class
 */
final class NameRegistryStateAccount implements BorshSerializable
{
    use IsBorshObject;

    public Buffer $data;

    private const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['parentName', 'pubkey'],
                ['owner', 'pubkey'],
                ['class', 'pubkey'],
            ],
        ],
    ];

    public const int SOL_RECORD_SIG_LEN = 96; // HEADER_LENGTH

    /**
     * @return array{registry: self, nftOwner: bool, nameAccountKey: PublicKey}
     * @throws SNSError
     * @throws AccountNotFoundException
     */
    public static function retrieve(Connection $connection, PublicKey|string $nameAccountKey): array
    {
        $nameAccountKey = PublicKey::from($nameAccountKey);

        $nameAccount = $connection->getAccountInfo($nameAccountKey);

        if ($nameAccount === []) {
            throw new SNSError(SNSError::AccountDoesNotExist);
        }

        $data = $nameAccount['data'] ?? null;
        $encoded = is_array($data) ? ($data[0] ?? null) : null;

        if (! is_string($encoded) || $encoded === '') {
            throw new SNSError(SNSError::NoAccountData);
        }

        $base64String = base64_decode($encoded, true);
        if ($base64String === false) {
            throw new SNSError(SNSError::NoAccountData);
        }

        $dataBuffer = Buffer::fromString($base64String);

        $registry = self::deserialize($dataBuffer);
        $registry->data = $dataBuffer->slice(self::SOL_RECORD_SIG_LEN);

        return [
            'registry' => $registry,
            'nftOwner' => false,
            'nameAccountKey' => $nameAccountKey,
        ];
    }

    public static function deserialize(Buffer $buffer): self
    {
        return Borsh::deserialize(
            schema: self::SCHEMA,
            class: self::class,
            buffer: $buffer,
        );
    }
}
