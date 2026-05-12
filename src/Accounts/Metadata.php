<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Accounts;

use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshObject;

/**
 * @property int $key
 * @property string $updateAuthority
 * @property string $mint
 * @property MetadataData $data
 * @property int $primarySaleHappened
 * @property int $isMutable
 */
final class Metadata implements BorshSerializable
{
    use IsBorshObject;

    private const array SCHEMA = [
        Creator::class => Creator::SCHEMA[Creator::class],
        MetadataData::class => MetadataData::SCHEMA[MetadataData::class],
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['key', 'u8'],
                ['updateAuthority', 'pubkeyAsString'],
                ['mint', 'pubkeyAsString'],
                ['data', MetadataData::class],
                ['primarySaleHappened', 'u8'], // bool
                ['isMutable', 'u8'], // bool
            ],
        ],
    ];

    /**
     * @param array<int, int> $buffer
     */
    public static function fromBuffer(array $buffer): self
    {
        return Borsh::deserialize(self::SCHEMA, self::class, $buffer);
    }
}
