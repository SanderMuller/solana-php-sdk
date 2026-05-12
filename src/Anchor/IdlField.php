<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Anchor;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;

/**
 * Single Borsh-encoded arg on an IDL instruction. `$type` is the raw IDL
 * type — `IdlEncoder` walks it at encode time.
 */
final readonly class IdlField
{
    /**
     * @param string|array<string, mixed> $type
     */
    public function __construct(
        public string $name,
        public string|array $type,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $name = $payload['name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new InputValidationException('IDL field missing name.');
        }

        $type = $payload['type'] ?? null;
        if (! is_string($type) && ! is_array($type)) {
            throw new InputValidationException("IDL field {$name} has unsupported type shape.");
        }

        /** @var string|array<string, mixed> $type */
        return new self(name: $name, type: $type);
    }
}
