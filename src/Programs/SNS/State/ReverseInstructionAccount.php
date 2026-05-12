<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SNS\State;

use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshObject;
use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;

final class ReverseInstructionAccount implements BorshSerializable
{
    use IsBorshObject;

    private int $tag = 12;

    private const array SCHEMA = [
        self::class => [
            'kind' => 'struct',
            'fields' => [
                ['tag', 'u8'],
                ['name', 'string'],
            ],
        ],
    ];

    public function __construct(private string $name) {}

    /**
     * @throws InputValidationException
     */
    public function getInstruction(
        PublicKey $programId,
        PublicKey $namingServiceProgram,
        PublicKey $rootDomain,
        PublicKey $reverseLookup,
        PublicKey $systemProgram,
        PublicKey $centralState,
        PublicKey $feePayer,
        PublicKey $rentSysvar,
        ?PublicKey $parentName = null,
        ?PublicKey $parentNameOwner = null
    ): TransactionInstruction {
        $keys = [
            new AccountMeta($programId, false, false),
            new AccountMeta($namingServiceProgram, false, false),
            new AccountMeta($rootDomain, false, false),
            new AccountMeta($reverseLookup, false, true),
            new AccountMeta($systemProgram, false, false),
            new AccountMeta($centralState, false, false),
            new AccountMeta($feePayer, true, true),
            new AccountMeta($rentSysvar, false, false),
        ];

        if ($parentName instanceof PublicKey) {
            $keys[] = new AccountMeta($parentName, false, true);
        }

        if ($parentNameOwner instanceof PublicKey) {
            $keys[] = new AccountMeta($parentNameOwner, true, true);
        }

        return new TransactionInstruction(
            programId: $programId,
            keys: $keys,
            data: Buffer::from($this->serialize()),
        );
    }

    /**
     * @param array<int, int> $buffer
     */
    public static function deserialize(array $buffer): self
    {
        return Borsh::deserialize(self::SCHEMA, self::class, $buffer);
    }

    /**
     * @return array<int, int>
     */
    public function serialize(): array
    {
        return Borsh::serialize(self::SCHEMA, $this);
    }
}
