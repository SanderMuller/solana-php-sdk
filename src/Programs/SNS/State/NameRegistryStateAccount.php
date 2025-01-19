<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SNS\State;

use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshObject;
use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Exceptions\SNSError;
use Collectiq\SolanaPhpSdk\Util\Buffer;

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
     * @throws SNSError
     * @throws AccountNotFoundException
     */
    public static function retrieve(Connection $connection, string $nameAccountKey): array
    {
        $nameAccount = $connection->getAccountInfo($nameAccountKey);

        if ($nameAccount === []) {
            throw new SNSError(SNSError::AccountDoesNotExist);
        }

        $base64String = base64_decode((string) $nameAccount['data'][0]);
        $dataBuffer = Buffer::fromString($base64String);

        $registry = self::deserialize($dataBuffer);

        $registry->data = $dataBuffer->slice(self::SOL_RECORD_SIG_LEN);
        // TODO: Implement retrieveNftOwner
        // $nftOwner = retrieveNftOwner($connection, $nameAccountKey);

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
