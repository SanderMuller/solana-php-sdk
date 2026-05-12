<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Anchor;

use Collectiq\SolanaPhpSdk\Borsh\BinaryWriter;
use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;

/**
 * One IDL instruction definition — discriminator, expected accounts (with
 * `writable`/`signer` flags), and ordered Borsh-encoded args.
 */
final readonly class IdlInstruction
{
    /**
     * @param list<int> $discriminator 8-byte instruction prefix.
     * @param list<IdlAccount> $accounts Order matches on-chain expectation.
     * @param list<IdlField> $args Order matches on-chain expectation.
     */
    public function __construct(
        public string $name,
        public string $programId,
        public array $discriminator,
        public array $accounts,
        public array $args,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload, string $programId): self
    {
        $name = $payload['name'];
        if (! is_string($name) || $name === '') {
            throw new InputValidationException('IDL instruction missing name.');
        }

        $discriminator = self::resolveDiscriminator($payload, $name);

        $accounts = [];
        foreach ($payload['accounts'] ?? [] as $entry) {
            if (! is_array($entry) || ! isset($entry['name']) || ! is_string($entry['name'])) {
                throw new InputValidationException("Malformed account entry on instruction {$name}.");
            }

            /** @var array<string, mixed> $entry */
            $accounts[] = IdlAccount::fromArray($entry);
        }

        $args = [];
        foreach ($payload['args'] ?? [] as $entry) {
            if (! is_array($entry) || ! isset($entry['name']) || ! is_string($entry['name'])) {
                throw new InputValidationException("Malformed arg entry on instruction {$name}.");
            }

            /** @var array<string, mixed> $entry */
            $args[] = IdlField::fromArray($entry);
        }

        return new self(
            name: $name,
            programId: $programId,
            discriminator: $discriminator,
            accounts: $accounts,
            args: $args,
        );
    }

    /**
     * Build a `TransactionInstruction` from a map of account pubkeys and a map
     * of arg values keyed by IDL name. Caller is responsible for supplying
     * every required account and arg — order is taken from the IDL.
     *
     * @param array<string, string|PublicKey> $accounts Map of IDL account name → pubkey.
     * @param array<string, mixed> $args Map of IDL arg name → value.
     */
    public function build(array $accounts, array $args = [], ?string $programIdOverride = null): TransactionInstruction
    {
        $keys = [];
        foreach ($this->accounts as $spec) {
            $resolved = $accounts[$spec->name] ?? $spec->fixedAddress ?? null;
            if ($resolved === null) {
                if ($spec->optional) {
                    continue;
                }

                throw new InputValidationException(
                    "Instruction {$this->name} requires account `{$spec->name}` but none was supplied.",
                );
            }

            $keys[] = new AccountMeta(
                publicKey: PublicKey::from($resolved),
                isSigner: $spec->signer,
                isWritable: $spec->writable,
            );
        }

        $writer = new BinaryWriter();
        foreach ($this->discriminator as $byte) {
            $writer->writeU8($byte);
        }

        foreach ($this->args as $field) {
            if (! array_key_exists($field->name, $args)) {
                throw new InputValidationException(
                    "Instruction {$this->name} missing required arg `{$field->name}`.",
                );
            }

            IdlEncoder::encode($field->type, $args[$field->name], $writer);
        }

        return new TransactionInstruction(
            programId: PublicKey::from($programIdOverride ?? $this->programId),
            keys: $keys,
            data: Buffer::fromArray($writer->toArray()),
        );
    }

    /**
     * Anchor 0.30+ embeds `discriminator` in the IDL. For older IDLs we fall
     * back to the documented `sha256("global:<name>")[:8]` derivation.
     *
     * @param array<string, mixed> $payload
     * @return list<int>
     */
    private static function resolveDiscriminator(array $payload, string $name): array
    {
        $declared = $payload['discriminator'] ?? null;
        if (is_array($declared) && count($declared) === 8) {
            $bytes = [];
            foreach ($declared as $byte) {
                if (! is_int($byte) || $byte < 0 || $byte > 255) {
                    throw new InputValidationException("Discriminator on {$name} contains non-byte values.");
                }

                $bytes[] = $byte;
            }

            /** @var list<int<0, 255>> $bytes */
            return $bytes;
        }

        $hash = hash('sha256', "global:{$name}", binary: true);
        $bytes = [];
        for ($i = 0; $i < 8; $i++) {
            $bytes[] = ord($hash[$i]);
        }

        /** @var list<int<0, 255>> $bytes */
        return $bytes;
    }
}
