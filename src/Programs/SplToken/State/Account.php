<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SplToken\State;

use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshObject;
use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\PublicKey;

/**
 * @property PublicKey|null $mint
 * @property PublicKey|null $owner
 * @property int $amount
 * @property int $delegateOption
 * @property PublicKey|null $delegate
 * @property int $state
 * @property int $isNativeOption
 * @property int $isNative
 * @property int $delegatedAmount
 * @property int $closeAuthorityOption
 * @property PublicKey|null $closeAuthority
 */
final class Account implements BorshSerializable
{
    use IsBorshObject;

    private static PublicKey $address;

    private static mixed $tlvData;

    private const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['mint', 'pubKey'],
                ['owner', 'pubKey'],
                ['amount', 'u64'],
                ['delegateOption', 'u32'],
                ['delegate', 'pubKey'],
                ['state', 'u8'],
                ['isNativeOption', 'u8'],
                ['isNative', 'u8'],
                ['delegatedAmount', 'u64'],
                ['closeAuthorityOption', 'u32'],
                ['closeAuthority', 'pubKey'],
            ],
        ],
    ];

    public static function fromBuffer(array $buffer): self
    {
        return Borsh::deserialize(self::SCHEMA, self::class, $buffer);
    }

    /**
     * @throws AccountNotFoundException
     */
    public static function getAccount(
        Connection $connection,
        PublicKey  $accountPublicKeyOnbject,
    ): Account {
        try {
            $info = $connection->getAccountInfo($accountPublicKeyOnbject);
            self::$address = $accountPublicKeyOnbject;
            self::$tlvData = $info['data'];
            $base64Data = $info['data']['0'];
            $base64String = base64_decode((string) $base64Data);
            $uint8Array = array_values(unpack('C*', $base64String));

            return self::fromBuffer($uint8Array);
        } catch (AccountNotFoundException) {
            throw new AccountNotFoundException();
        }
    }
}
