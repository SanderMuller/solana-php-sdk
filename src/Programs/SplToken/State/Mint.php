<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs\SplToken\State;

use SanderMuller\SolanaPhpSdk\Borsh\Borsh;
use SanderMuller\SolanaPhpSdk\Borsh\BorshSerializable;
use SanderMuller\SolanaPhpSdk\Borsh\IsBorshObject;

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
