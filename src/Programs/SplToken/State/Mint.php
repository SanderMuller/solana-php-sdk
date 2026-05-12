<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SplToken\State;

use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshObject;

final class Mint implements BorshSerializable
{
    use IsBorshObject;

    private const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['mintAuthorityOption', 'u32'],
                ['mintAuthority', 'pubKey'],
                ['supply', 'u64'],
                ['decimals', 'u8'],
                ['isInitialized', 'u8'],
                ['freezeAuthorityOption', 'u32'],
                ['freezeAuthority', 'pubKey'],
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
