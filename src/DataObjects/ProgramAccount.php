<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

use ArrayAccess;
use BadMethodCallException;
use SanderMuller\SolanaPhpSdk\PublicKey;

/**
 * Single row from a `getProgramAccounts` response: a pubkey plus the
 * account state at that address. The legacy RPC shape is
 * `{pubkey, account: {lamports, owner, executable, rentEpoch, data}}` —
 * this DTO normalises the `account` payload into an {@see AccountInfo}
 * and surfaces `pubkey` as a {@see PublicKey}. Implements `ArrayAccess`
 * so legacy `$row['pubkey']` / `$row['account']` keys keep working.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ProgramAccount implements ArrayAccess
{
    public function __construct(
        public PublicKey $pubkey,
        public AccountInfo $account,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $pubkey = $row['pubkey'] ?? null;
        $account = $row['account'] ?? null;

        /** @var array<string, mixed> $accountArray */
        $accountArray = is_array($account) ? $account : [];

        return new self(
            pubkey: PublicKey::from(is_string($pubkey) ? $pubkey : '11111111111111111111111111111111'),
            account: AccountInfo::fromValue($accountArray),
        );
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['pubkey', 'account'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'pubkey' => $this->pubkey->toBase58(),
            'account' => $this->account,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new BadMethodCallException('ProgramAccount is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new BadMethodCallException('ProgramAccount is immutable.');
    }
}
