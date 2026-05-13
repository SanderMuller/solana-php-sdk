<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

use ArrayAccess;
use BadMethodCallException;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

/**
 * Typed wrapper over the `getAccountInfo` / `getMultipleAccounts` value
 * payload. The on-the-wire shape is `{lamports, owner, executable, rentEpoch,
 * space?, data: [base64, "base64"] | [string, "base58"] | parsed-array}`.
 * This DTO normalises the encoding tuple into a decoded {@see Buffer} plus
 * the original encoding label, and exposes the well-known fields as typed
 * properties. Implements `ArrayAccess` so legacy `$info['owner']` callers
 * keep working.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class AccountInfo implements ArrayAccess
{
    /**
     * @param array<int|string, mixed>|null $rawData Untouched payload —
     *        kept so `jsonParsed` responses (which return a structured object
     *        instead of an encoding tuple) are still reachable when the caller
     *        needs them.
     */
    public function __construct(
        public int $lamports,
        public PublicKey $owner,
        public bool $executable,
        public int $rentEpoch,
        public ?int $space,
        public ?Buffer $data,
        public ?string $dataEncoding,
        public ?array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $value RPC `value` payload.
     */
    public static function fromValue(array $value): self
    {
        $lamports = $value['lamports'] ?? 0;
        $owner = $value['owner'] ?? '11111111111111111111111111111111';
        $executable = $value['executable'] ?? false;
        $rentEpoch = $value['rentEpoch'] ?? 0;
        $space = $value['space'] ?? null;

        [$data, $encoding] = self::decodeData($value['data'] ?? null);

        $rawData = $value['data'] ?? null;

        return new self(
            lamports: is_int($lamports) ? $lamports : (is_numeric($lamports) ? (int) $lamports : 0),
            owner: PublicKey::from(is_string($owner) ? $owner : '11111111111111111111111111111111'),
            executable: (bool) $executable,
            rentEpoch: is_int($rentEpoch) ? $rentEpoch : (is_numeric($rentEpoch) ? (int) $rentEpoch : 0),
            space: is_int($space) ? $space : null,
            data: $data,
            dataEncoding: $encoding,
            rawData: is_array($rawData) ? $rawData : null,
        );
    }

    /**
     * @return array{0: Buffer|null, 1: string|null}
     */
    private static function decodeData(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return [null, null];
        }

        // `[bytes, encoding]` tuple form. `jsonParsed` returns a parsed map
        // instead, in which case we leave `$data` null and surface the map
        // through `$rawData`.
        if (array_is_list($raw) && count($raw) === 2 && is_string($raw[0]) && is_string($raw[1])) {
            $payload = $raw[0];
            $encoding = $raw[1];

            return match ($encoding) {
                'base64' => [self::decodeBase64($payload), 'base64'],
                'base64+zstd' => [self::decodeBase64Zstd($payload), 'base64+zstd'],
                'base58' => [Buffer::fromBase58($payload), 'base58'],
                default => [null, $encoding],
            };
        }

        return [null, 'jsonParsed'];
    }

    private static function decodeBase64(string $payload): ?Buffer
    {
        $raw = base64_decode($payload, strict: true);

        return $raw === false ? null : Buffer::from($raw);
    }

    /**
     * Decode the `base64+zstd` account-data encoding. Requires the `zstd`
     * PHP extension (https://github.com/kjdev/php-ext-zstd); when the
     * extension is missing or decompression fails, returns null so the
     * caller can fall back on `$rawData` / `$dataEncoding` to surface the
     * unrecoverable payload explicitly.
     */
    private static function decodeBase64Zstd(string $payload): ?Buffer
    {
        $raw = base64_decode($payload, strict: true);
        if ($raw === false || ! function_exists('zstd_uncompress')) {
            return null;
        }

        $uncompressed = zstd_uncompress($raw);

        return $uncompressed === false ? null : Buffer::from($uncompressed);
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array(
            $offset,
            ['lamports', 'owner', 'executable', 'rentEpoch', 'space', 'data'],
            true,
        );
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'lamports' => $this->lamports,
            'owner' => $this->owner->toBase58(),
            'executable' => $this->executable,
            'rentEpoch' => $this->rentEpoch,
            'space' => $this->space,
            'data' => $this->rawData,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new BadMethodCallException('AccountInfo is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new BadMethodCallException('AccountInfo is immutable.');
    }
}
