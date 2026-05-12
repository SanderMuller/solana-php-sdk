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

    /**
     * @var array<class-string, array<string, mixed>>
     */
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

    /**
     * @param array<int, int> $buffer
     */
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
        $info = $connection->getAccountInfo($accountPublicKeyOnbject);
        $data = $info['data'] ?? null;

        if (! is_array($data) || ! isset($data[0]) || ! is_string($data[0])) {
            throw new AccountNotFoundException('Account data missing or not base64-encoded.');
        }

        $binary = base64_decode($data[0], true);
        if ($binary === false) {
            throw new AccountNotFoundException('Account data is not valid base64.');
        }

        $unpacked = unpack('C*', $binary);
        /** @var array<int, int> $bytes */
        $bytes = $unpacked === false ? [] : array_values($unpacked);

        return self::fromBuffer($bytes);
    }

    public function getMint(): ?PublicKey
    {
        return $this->mint instanceof PublicKey ? $this->mint : null;
    }

    public function getOwner(): ?PublicKey
    {
        return $this->owner instanceof PublicKey ? $this->owner : null;
    }

    public function getDelegate(): ?PublicKey
    {
        return $this->delegate instanceof PublicKey ? $this->delegate : null;
    }

    public function getCloseAuthority(): ?PublicKey
    {
        return $this->closeAuthority instanceof PublicKey ? $this->closeAuthority : null;
    }

    public function getNativeBalance(): int
    {
        return (int) $this->isNative;
    }
}
