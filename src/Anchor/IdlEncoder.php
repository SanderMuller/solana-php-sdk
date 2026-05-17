<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Anchor;

use SanderMuller\SolanaPhpSdk\Borsh\BinaryReader;
use SanderMuller\SolanaPhpSdk\Borsh\BinaryWriter;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\PublicKey;

/**
 * Encodes a single IDL-typed value into the supplied `BinaryWriter` using
 * Anchor's Borsh layout rules.
 *
 * Supported shapes (Anchor 0.30 IDL):
 *  - Primitive name string: `'u8'..'u128'`, `'i8'..'i128'`, `'f32'`/`'f64'`,
 *    `'bool'`, `'string'`, `'bytes'`, `'pubkey'`.
 *  - `['vec'  => <inner>]`            — `u32` length prefix + items.
 *  - `['array'=> [<inner>, <length>]]` — fixed-length, no prefix.
 *  - `['option'=> <inner>]`          — `u8` tag (0/1) + maybe payload.
 *  - `['defined'=> ['name' => ...]]` — user-defined struct (not handled here;
 *    callers must pre-encode complex defined types, or this method throws).
 */
final class IdlEncoder
{
    /**
     * Decode the args of an {@see IdlInstruction} from a Borsh-encoded
     * reader (positioned past the 8-byte discriminator), returning a
     * name-keyed map. Falls back to {@see IdlTypeDecoder} for
     * user-defined types referenced via `{defined: {name: ...}}`.
     *
     * @return array<string, mixed>
     */
    public static function decode(IdlInstruction $ix, BinaryReader $reader, ?AnchorIdl $idl = null): array
    {
        $typeDecoder = $idl === null ? null : new IdlTypeDecoder($idl);

        $out = [];
        foreach ($ix->args as $field) {
            $out[$field->name] = self::decodeType($field->type, $reader, $typeDecoder);
        }

        return $out;
    }

    /**
     * Single-value decode counterpart to {@see encode()}.
     *
     * @param string|array<string, mixed> $type
     */
    public static function decodeType(string|array $type, BinaryReader $reader, ?IdlTypeDecoder $typeDecoder = null): mixed
    {
        if (is_array($type) && isset($type['defined'])) {
            if ($typeDecoder === null) {
                $defined = $type['defined'];
                $name = is_array($defined) ? ($defined['name'] ?? null) : $defined;
                $rendered = is_string($name) ? $name : '<unknown>';
                throw new InputValidationException(
                    "Defined IDL type `{$rendered}` requires an AnchorIdl context — pass the IDL to IdlEncoder::decode().",
                );
            }

            return $typeDecoder->decode($type, $reader);
        }

        if ($typeDecoder !== null) {
            return $typeDecoder->decode($type, $reader);
        }

        // No IDL context — decode primitives + composites directly via a transient decoder
        // that will explode if it encounters a `defined` reference (handled above).
        return self::decodePrimitiveOrComposite($type, $reader);
    }

    /**
     * @param string|array<string, mixed> $type
     */
    private static function decodePrimitiveOrComposite(string|array $type, BinaryReader $reader): mixed
    {
        if (is_string($type)) {
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
                'u128', 'i128' => throw new InputValidationException("IDL type `{$type}` is not yet supported by IdlEncoder::decode()."),
                default => throw new InputValidationException("Unknown IDL primitive `{$type}`."),
            };
        }

        if (isset($type['vec'])) {
            $length = $reader->readU32();
            $out = [];
            for ($i = 0; $i < $length; $i++) {
                $out[] = self::decodePrimitiveOrComposite(self::narrowInner($type['vec']), $reader);
            }

            return $out;
        }

        if (isset($type['array']) && is_array($type['array']) && count($type['array']) === 2) {
            $length = $type['array'][1];
            if (! is_int($length)) {
                throw new InputValidationException('IDL `array` length must be an integer.');
            }
            $out = [];
            for ($i = 0; $i < $length; $i++) {
                $out[] = self::decodePrimitiveOrComposite(self::narrowInner($type['array'][0]), $reader);
            }

            return $out;
        }

        if (isset($type['option'])) {
            $tag = $reader->readU8();

            return $tag === 0 ? null : self::decodePrimitiveOrComposite(self::narrowInner($type['option']), $reader);
        }

        throw new InputValidationException('Unsupported IDL type shape: ' . json_encode($type));
    }

    /**
     * @param string|array<string, mixed> $type
     */
    public static function encode(string|array $type, mixed $value, BinaryWriter $writer): void
    {
        if (is_string($type)) {
            self::encodePrimitive($type, $value, $writer);

            return;
        }

        if (isset($type['vec'])) {
            self::encodeVec(self::narrowInner($type['vec']), $value, $writer);

            return;
        }

        if (isset($type['array']) && is_array($type['array']) && count($type['array']) === 2) {
            self::encodeArray(self::narrowInner($type['array'][0]), $type['array'][1], $value, $writer);

            return;
        }

        if (isset($type['option'])) {
            self::encodeOption(self::narrowInner($type['option']), $value, $writer);

            return;
        }

        if (isset($type['defined'])) {
            $defined = $type['defined'];
            $name = is_array($defined) ? ($defined['name'] ?? null) : $defined;
            $rendered = is_string($name) ? $name : '<unknown>';
            throw new InputValidationException(
                "Defined IDL type `{$rendered}` cannot be auto-encoded — pre-serialise it (e.g. via Borsh) and pass the resulting byte array instead.",
            );
        }

        throw new InputValidationException('Unsupported IDL type shape: ' . json_encode($type));
    }

    private static function encodePrimitive(string $type, mixed $value, BinaryWriter $writer): void
    {
        switch ($type) {
            case 'u8':
                $writer->writeU8(self::int($value));

                return;
            case 'u16':
                $writer->writeU16(self::int($value));

                return;
            case 'u32':
                $writer->writeU32(self::int($value));

                return;
            case 'u64':
                $writer->writeU64(self::int($value));

                return;
            case 'i8':
                $writer->writeI8(self::int($value));

                return;
            case 'i16':
                $writer->writeI16(self::int($value));

                return;
            case 'i32':
                $writer->writeI32(self::int($value));

                return;
            case 'i64':
                $writer->writeI64(self::int($value));

                return;
            case 'f32':
                $writer->writeF32(self::float($value));

                return;
            case 'f64':
                $writer->writeF64(self::float($value));

                return;
            case 'bool':
                $writer->writeU8($value === true ? 1 : 0);

                return;
            case 'string':
                $writer->writeString(self::stringValue($value));

                return;
            case 'bytes':
                $bytes = self::byteArray($value);
                $writer->writeU32(count($bytes));
                $writer->writeFixedArray($bytes);

                return;
            case 'pubkey':
            case 'publicKey':
                $writer->writeFixedArray(PublicKey::from(self::pubkeyValue($value))->toBytes());

                return;
            case 'u128':
            case 'i128':
                throw new InputValidationException("IDL type `{$type}` is not yet supported by IdlEncoder.");
            default:
                throw new InputValidationException("Unknown IDL primitive `{$type}`.");
        }
    }

    /**
     * @param string|array<string, mixed> $inner
     */
    private static function encodeVec(string|array $inner, mixed $value, BinaryWriter $writer): void
    {
        if (! is_array($value)) {
            throw new InputValidationException('IDL `vec` value must be an array.');
        }

        $writer->writeU32(count($value));
        foreach ($value as $item) {
            self::encode($inner, $item, $writer);
        }
    }

    /**
     * @param string|array<string, mixed> $inner
     */
    private static function encodeArray(string|array $inner, mixed $length, mixed $value, BinaryWriter $writer): void
    {
        if (! is_int($length)) {
            throw new InputValidationException('IDL `array` length must be an integer.');
        }

        if (! is_array($value) || count($value) !== $length) {
            $given = is_array($value) ? count($value) : 'non-array';
            throw new InputValidationException("IDL array expected {$length} items, got {$given}.");
        }

        foreach ($value as $item) {
            self::encode($inner, $item, $writer);
        }
    }

    /**
     * @param string|array<string, mixed> $inner
     */
    private static function encodeOption(string|array $inner, mixed $value, BinaryWriter $writer): void
    {
        if ($value === null) {
            $writer->writeU8(0);

            return;
        }

        $writer->writeU8(1);
        self::encode($inner, $value, $writer);
    }

    private static function int(mixed $value): int
    {
        if (! is_int($value)) {
            throw new InputValidationException('Expected int value for IDL primitive.');
        }

        return $value;
    }

    private static function float(mixed $value): float
    {
        if (! is_float($value) && ! is_int($value)) {
            throw new InputValidationException('Expected float value for IDL primitive.');
        }

        return (float) $value;
    }

    private static function stringValue(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InputValidationException('Expected string value for IDL primitive.');
        }

        return $value;
    }

    /**
     * @return list<int<0, 255>>
     */
    private static function byteArray(mixed $value): array
    {
        if (is_string($value)) {
            $bytes = [];
            $length = strlen($value);
            for ($i = 0; $i < $length; $i++) {
                $bytes[] = ord($value[$i]);
            }

            /** @var list<int<0, 255>> $bytes */
            return $bytes;
        }

        if (is_array($value)) {
            $bytes = [];
            foreach ($value as $byte) {
                if (! is_int($byte) || $byte < 0 || $byte > 255) {
                    throw new InputValidationException('IDL `bytes` array must contain 0..255 integers.');
                }

                $bytes[] = $byte;
            }

            /** @var list<int<0, 255>> $bytes */
            return $bytes;
        }

        throw new InputValidationException('IDL `bytes` value must be a string or array of bytes.');
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

    private static function pubkeyValue(mixed $value): string|PublicKey
    {
        if ($value instanceof PublicKey || is_string($value)) {
            return $value;
        }

        throw new InputValidationException('IDL `pubkey` value must be a string or PublicKey.');
    }
}
