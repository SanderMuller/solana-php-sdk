<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Accounts;

use SanderMuller\SolanaPhpSdk\Borsh\IsBorshDeserializable;

final class Creator
{
    use IsBorshDeserializable;

    public const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['address', 'pubkeyAsString'],
                ['verified', 'u8'],
                ['share', 'u8'],
            ],
        ],
    ];
}
