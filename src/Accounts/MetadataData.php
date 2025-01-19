<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Accounts;

use Collectiq\SolanaPhpSdk\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;

final class MetadataData implements BorshSerializable
{
    use Borsh\IsBorshObject;

    public const array SCHEMA = [
        Creator::class => Creator::SCHEMA[Creator::class],
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['name', 'string'],
                ['symbol', 'string'],
                ['uri', 'string'],
                ['sellerFeeBasisPoints', 'u16'],
                ['creators', [
                    'kind' => 'option',
                    'type' => [Creator::class],
                ]],
            ],
        ],
    ];

    public function __set($name, $value): void
    {
        $this->{$name} = is_string($value) ? preg_replace('/[[:cntrl:]]/', '', $value) : $value;
    }
}
