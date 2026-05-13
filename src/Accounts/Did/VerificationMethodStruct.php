<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Accounts\Did;

use SanderMuller\SolanaPhpSdk\Borsh\BorshSerializable;
use SanderMuller\SolanaPhpSdk\Borsh\IsBorshObject;

final class VerificationMethodStruct implements BorshSerializable
{
    use IsBorshObject;

    public const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['fragment', 'string'],
                ['flags', 'u16'],
                ['methodType', 'u8'],
                ['keyData', 'bytes'],
            ],
        ],
    ];
}
