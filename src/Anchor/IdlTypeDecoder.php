<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Anchor;

use SanderMuller\SolanaPhpSdk\Borsh\BinaryReader;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;

/**
 * Recursive Borsh decoder for IDL types, including user-defined
 * structs and tagged-union enums referenced via
 * `{defined: {name: ...}}`. Stateful — holds the {@see AnchorIdl} so
 * the recursion can dereference user type names against
 * `AnchorIdl::$types`.
 *
 * Tagged-union enums are emitted as `{variantName: payload | true}` —
 * one-key arrays mirroring the Anchor / JS / Rust idiom. Variants with
 * no fields decode to `true` so the consumer can `array_key_first()`
 * the variant name regardless of payload.
 *
 * @api
 */
final class IdlTypeDecoder
{
    public function __construct(private readonly AnchorIdl $idl) {}

    /**
     * @param string|array<string, mixed> $type
     */
    public function decode(string|array $type, BinaryReader $reader): mixed
    {
        if (is_string($type)) {
            return self::decodePrimitive($type, $reader);
        }

        if (isset($type['vec'])) {
            return $this->decodeVec(self::narrowInner($type['vec']), $reader);
        }

        if (isset($type['array']) && is_array($type['array']) && count($type['array']) === 2) {
            return $this->decodeArray(self::narrowInner($type['array'][0]), $type['array'][1], $reader);
        }

        if (isset($type['option'])) {
            return $this->decodeOption(self::narrowInner($type['option']), $reader);
        }

        if (isset($type['defined'])) {
            return $this->decodeDefined($type['defined'], $reader);
        }

        throw new InputValidationException('Unsupported IDL type shape: ' . json_encode($type));
    }

    private static function decodePrimitive(string $type, BinaryReader $reader): mixed
    {
        return match ($type) {
            'u8' => $reader->readU8(),
            'u16' => $reader->readU16(),
            'u32' => $reader->readU32(),
            'u64' => $reader->readU64(),
            'i8' => $reader->readI8(),
            'i16' => $reader->readI16(),
            'i32' => $reader->readI32(),
            'i64' => $reader->readI64(),
            'f32' => $reader->readF32(),
            'f64' => $reader->readF64(),
            'bool' => $reader->readU8() === 1,
            'string' => $reader->readString(),
            'bytes' => $reader->readFixedArray($reader->readU32()),
            'pubkey', 'publicKey' => $reader->readPubKeyAsString(),
            'u128', 'i128' => throw new InputValidationException("IDL type `{$type}` is not yet supported by IdlTypeDecoder."),
            default => throw new InputValidationException("Unknown IDL primitive `{$type}`."),
        };
    }

    /**
     * @param string|array<string, mixed> $inner
     * @return list<mixed>
     */
    private function decodeVec(string|array $inner, BinaryReader $reader): array
    {
        $length = $reader->readU32();
        $out = [];
        for ($i = 0; $i < $length; $i++) {
            $out[] = $this->decode($inner, $reader);
        }

        return $out;
    }

    /**
     * @param string|array<string, mixed> $inner
     * @return list<mixed>
     */
    private function decodeArray(string|array $inner, mixed $length, BinaryReader $reader): array
    {
        if (! is_int($length)) {
            throw new InputValidationException('IDL `array` length must be an integer.');
        }

        $out = [];
        for ($i = 0; $i < $length; $i++) {
            $out[] = $this->decode($inner, $reader);
        }

        return $out;
    }

    /**
     * @param string|array<string, mixed> $inner
     */
    private function decodeOption(string|array $inner, BinaryReader $reader): mixed
    {
        $tag = $reader->readU8();

        return $tag === 0 ? null : $this->decode($inner, $reader);
    }

    private function decodeDefined(mixed $defined, BinaryReader $reader): mixed
    {
        $name = is_array($defined) ? ($defined['name'] ?? null) : $defined;
        if (! is_string($name) || $name === '') {
            throw new InputValidationException('IDL `defined` reference must carry a name.');
        }

        $typeDef = $this->idl->types[$name] ?? null;
        if (! is_array($typeDef)) {
            throw new InputValidationException("IDL user type `{$name}` is not registered on the AnchorIdl.");
        }

        /** @var array<string, mixed> $inner */
        $inner = is_array($typeDef['type'] ?? null) ? $typeDef['type'] : $typeDef;
        $kind = $inner['kind'] ?? null;

        return match ($kind) {
            'struct' => $this->decodeStruct($inner, $reader),
            'enum' => $this->decodeEnum($inner, $name, $reader),
            default => throw new InputValidationException("IDL user type `{$name}` has unsupported kind `" . (is_string($kind) ? $kind : 'null') . '`.'),
        };
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function decodeStruct(array $inner, BinaryReader $reader): array
    {
        $fields = is_array($inner['fields'] ?? null) ? $inner['fields'] : [];

        $out = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ! is_string($field['name'] ?? null)) {
                throw new InputValidationException('Malformed struct field on IDL user type.');
            }

            $type = $field['type'] ?? null;
            if (! is_string($type) && ! is_array($type)) {
                throw new InputValidationException("Struct field {$field['name']} has unsupported type shape.");
            }

            /** @var string|array<string, mixed> $type */
            $out[$field['name']] = $this->decode($type, $reader);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function decodeEnum(array $inner, string $enumName, BinaryReader $reader): array
    {
        $variants = is_array($inner['variants'] ?? null) ? $inner['variants'] : [];

        $tag = $reader->readU8();
        if (! isset($variants[$tag])) {
            throw new InputValidationException("IDL enum `{$enumName}` has no variant at tag {$tag}.");
        }

        $variant = $variants[$tag];
        if (! is_array($variant) || ! is_string($variant['name'] ?? null)) {
            throw new InputValidationException("IDL enum `{$enumName}` variant {$tag} is malformed.");
        }

        $variantName = $variant['name'];
        $fields = $variant['fields'] ?? null;

        if (! is_array($fields) || $fields === []) {
            return [$variantName => true];
        }

        // Anchor enum variants can carry either a struct (named fields) or
        // a tuple (positional fields). We mirror Anchor's JSON shape: named
        // fields decode to `{name => value}`, tuples to a positional list.
        $first = $fields[0] ?? null;
        $isTuple = is_string($first) || (is_array($first) && ! isset($first['name']));

        if ($isTuple) {
            $payload = [];
            foreach ($fields as $field) {
                if (! is_string($field) && ! is_array($field)) {
                    throw new InputValidationException("Malformed tuple variant on enum `{$enumName}`.");
                }

                /** @var string|array<string, mixed> $field */
                $payload[] = $this->decode($field, $reader);
            }
        } else {
            $payload = [];
            foreach ($fields as $field) {
                if (! is_array($field) || ! is_string($field['name'] ?? null)) {
                    throw new InputValidationException("Malformed struct variant on enum `{$enumName}`.");
                }

                $type = $field['type'] ?? null;
                if (! is_string($type) && ! is_array($type)) {
                    throw new InputValidationException("Variant field {$field['name']} has unsupported type shape.");
                }

                /** @var string|array<string, mixed> $type */
                $payload[$field['name']] = $this->decode($type, $reader);
            }
        }

        return [$variantName => $payload];
    }

    /**
     * @return string|array<string, mixed>
     */
    private static function narrowInner(mixed $inner): string|array
    {
        if (is_string($inner)) {
            return $inner;
        }

        if (is_array($inner)) {
            /** @var array<string, mixed> $inner */
            return $inner;
        }

        throw new InputValidationException('IDL inner type must be a string or object.');
    }
}
