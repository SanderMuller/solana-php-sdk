<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Accounts;

use Collectiq\SolanaPhpSdk\Accounts\Did\ServiceStruct;
use Collectiq\SolanaPhpSdk\Accounts\Did\VerificationMethodStruct;
use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshObject;

final class DidData implements BorshSerializable
{
    use IsBorshObject;

    public $keyData;

    private const array SCHEMA = [
        VerificationMethodStruct::class => VerificationMethodStruct::SCHEMA[VerificationMethodStruct::class],
        ServiceStruct::class => ServiceStruct::SCHEMA[ServiceStruct::class],
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['offset', 'u64'],
                ['version', 'u8'],
                ['bump', 'u8'],
                ['nonce', 'u64'],
                ['initialVerificationMethod', 'string'],
                ['flags', 'u16'],
                ['methodType', 'u8'],
                ['keyData', ['u8']],
                ['verificationMethods', [VerificationMethodStruct::class]],
                ['services', [ServiceStruct::class]],
                ['nativeControllers', ['pubKey']],
                ['otherControllers', ['string']],
            ],
        ],
    ];

    public static function fromBuffer(array $buffer): self
    {
        return Borsh::deserialize(self::SCHEMA, self::class, $buffer);
    }
}
