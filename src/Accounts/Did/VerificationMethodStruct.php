<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Accounts\Did;

use Collectiq\SolanaPhpSdk\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;

final class VerificationMethodStruct implements BorshSerializable
{
    use Borsh\IsBorshObject;

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
