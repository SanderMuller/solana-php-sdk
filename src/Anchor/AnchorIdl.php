<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Anchor;

use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use JsonException;

/**
 * Anchor IDL (Interface Definition Language) — runtime representation of a
 * deployed Anchor program's public interface. Anchor 0.30+ ships the
 * 8-byte instruction discriminators directly in the IDL; older formats are
 * normalised on load (computed from `sha256("global:<name>")`).
 *
 * Typical use:
 *
 *   $idl = AnchorIdl::fromFile(__DIR__ . '/my_program.json');
 *   $ix  = $idl->instruction('initialize')->build(
 *       accounts: ['state' => $state, 'user' => $user],
 *       args:     ['amount' => 1_000_000, 'name' => 'collectiq'],
 *   );
 *
 * The returned `TransactionInstruction` can be fed into `Transaction` or
 * `MessageV0::compile()`.
 *
 * @see https://www.anchor-lang.com/docs/basics/idl
 */
final readonly class AnchorIdl
{
    /**
     * @param array<string, IdlInstruction> $instructions Keyed by instruction name (camelCase, as in the IDL).
     * @param array<string, array<string, mixed>> $types Custom user types referenced via `{defined: {name}}`.
     */
    public function __construct(
        public string $programId,
        public string $name,
        public ?string $version,
        public array $instructions,
        public array $types,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        $address = $payload['address'] ?? $metadata['address'] ?? null;
        if (! is_string($address) || $address === '') {
            throw new InputValidationException('IDL is missing a program address.');
        }

        $name = $metadata['name'] ?? $payload['name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new InputValidationException('IDL is missing a program name.');
        }

        $version = null;
        $metadataVersion = $metadata['version'] ?? null;
        if (is_string($metadataVersion)) {
            $version = $metadataVersion;
        } elseif (is_string($payload['version'] ?? null)) {
            $version = $payload['version'];
        }

        $types = [];
        $typesPayload = $payload['types'] ?? [];
        if (is_array($typesPayload)) {
            foreach ($typesPayload as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $entryName = $entry['name'] ?? null;
                if (! is_string($entryName) || $entryName === '') {
                    continue;
                }

                /** @var array<string, mixed> $entry */
                $types[$entryName] = $entry;
            }
        }

        $instructions = [];
        $instructionsPayload = $payload['instructions'] ?? [];
        if (! is_array($instructionsPayload)) {
            throw new InputValidationException('IDL `instructions` must be an array.');
        }

        foreach ($instructionsPayload as $entry) {
            if (! is_array($entry) || ! is_string($entry['name'] ?? null)) {
                throw new InputValidationException('Malformed IDL instruction entry.');
            }

            /** @var array<string, mixed> $entry */
            $instruction = IdlInstruction::fromArray($entry, $address);
            $instructions[$instruction->name] = $instruction;
        }

        return new self(
            programId: $address,
            name: $name,
            version: $version,
            instructions: $instructions,
            types: $types,
        );
    }

    public static function fromJson(string $json): self
    {
        try {
            $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InputValidationException('IDL JSON is malformed: ' . $jsonException->getMessage(), 0, $jsonException);
        }

        if (! is_array($payload)) {
            throw new InputValidationException('IDL JSON must decode to an object.');
        }

        /** @var array<string, mixed> $payload */
        return self::fromArray($payload);
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InputValidationException("IDL file not readable: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new GenericException("Failed reading IDL file: {$path}");
        }

        return self::fromJson($contents);
    }

    public function instruction(string $name): IdlInstruction
    {
        return $this->instructions[$name]
            ?? throw new InputValidationException("Instruction {$name} not defined on program {$this->name}.");
    }

    public function hasInstruction(string $name): bool
    {
        return isset($this->instructions[$name]);
    }

    /**
     * @return list<string>
     */
    public function instructionNames(): array
    {
        return array_keys($this->instructions);
    }
}
