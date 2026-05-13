<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Accounts\Did;

use SanderMuller\SolanaPhpSdk\Borsh\BorshSerializable;
use SanderMuller\SolanaPhpSdk\Borsh\IsBorshObject;

final class ServiceStruct implements BorshSerializable
{
    use IsBorshObject;

    public const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['fragment', 'string'],
                ['serviceType', 'string'],
                ['serviceEndpoint', 'string'],
            ],
        ],
    ];
}
