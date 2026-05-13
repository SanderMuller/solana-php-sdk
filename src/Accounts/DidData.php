<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Accounts;

use SanderMuller\SolanaPhpSdk\Accounts\Did\ServiceStruct;
use SanderMuller\SolanaPhpSdk\Accounts\Did\VerificationMethodStruct;
use SanderMuller\SolanaPhpSdk\Borsh\Borsh;
use SanderMuller\SolanaPhpSdk\Borsh\BorshSerializable;
use SanderMuller\SolanaPhpSdk\Borsh\IsBorshObject;

final class DidData implements BorshSerializable
{
    use IsBorshObject;

    /**
     * Authority pubkey bytes — populated by Borsh deserialisation as a u8[]
     * byte array, then re-encoded to base58 by {@see Programs\DidSolProgram::deserializeDidData()}.
     *
     * @var array<int, int>|string|null
     */
    public array|string|null $keyData = null;

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

    /**
     * @param array<int, int> $buffer
     */
    public static function fromBuffer(array $buffer): self
    {
        return Borsh::deserialize(self::SCHEMA, self::class, $buffer);
    }
}
