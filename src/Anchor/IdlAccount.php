<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Anchor;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;

/**
 * Account spec for an IDL instruction. Mirrors the Anchor 0.30+ shape but
 * also accepts the legacy `isMut`/`isSigner` keys for older IDLs.
 */
final readonly class IdlAccount
{
    public function __construct(
        public string $name,
        public bool $writable,
        public bool $signer,
        public bool $optional = false,
        public ?string $fixedAddress = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $name = $payload['name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new InputValidationException('IDL account missing name.');
        }

        $writable = self::boolKey($payload, 'writable', fallback: 'isMut');
        $signer = self::boolKey($payload, 'signer', fallback: 'isSigner');
        $optional = self::boolKey($payload, 'optional', fallback: 'isOptional');

        $fixedAddress = null;
        if (isset($payload['address']) && is_string($payload['address']) && $payload['address'] !== '') {
            $fixedAddress = $payload['address'];
        }

        return new self(
            name: $name,
            writable: $writable,
            signer: $signer,
            optional: $optional,
            fixedAddress: $fixedAddress,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function boolKey(array $payload, string $modern, string $fallback): bool
    {
        if (array_key_exists($modern, $payload)) {
            return $payload[$modern] === true;
        }

        if (array_key_exists($fallback, $payload)) {
            return $payload[$fallback] === true;
        }

        return false;
    }
}
