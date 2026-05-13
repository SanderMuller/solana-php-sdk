<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Borsh;

use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

/**
 * Borsh (https://borsh.io) (de)serializer with Solana-flavoured extensions
 * (`pubkey`, `pubkeyAsString`).
 *
 * Schemas have the shape:
 *
 *   [
 *     ClassName::class => [
 *       'kind' => 'struct' | 'enum',
 *       'fields' => [[$name, $fieldType], ...],   // for struct
 *       'values' => [[$variantName, $fieldType], ...],  // for enum
 *       'field'  => 'enum',  // optional; property name holding variant index
 *     ],
 *     ...
 *   ]
 *
 * `$fieldType` is one of:
 *   - primitive name: `'u8'..'u64'`, `'i8'..'i64'`, `'f32'`/`'f64'`, `'string'`, `'pubkey'`/`'pubKey'`, `'pubkeyAsString'`
 *   - class-string of another schema entry
 *   - `[<elemType>]`            — dynamic-length array
 *   - `[<elemType>, <length>]`  — fixed-length array
 *   - `[<length>]`              — raw byte array of that length
 *   - `['kind' => 'option', 'type' => <elemType>]`  — option<T>
 */
final class Borsh
{
    /**
     * @param array<class-string, array<string, mixed>> $schema
     * @return array<int, int>
     */
    public static function serialize(array $schema, object $object): array
    {
        $writer = new BinaryWriter();

        self::serializeObject(
            schema: $schema,
            object: $object,
            writer: $writer,
        );

        return $writer->toArray();
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     */
    private static function serializeObject(array $schema, object $object, BinaryWriter $writer): void
    {
        $objectSchema = $schema[$object::class] ?? null;
        if (! is_array($objectSchema)) {
            $class = $object::class;
            throw new BorshException("Class {$class} is missing in schema");
        }

        $kind = $objectSchema['kind'] ?? null;

        if ($kind === 'struct') {
            $fields = $objectSchema['fields'] ?? [];
            if (! is_array($fields)) {
                throw new BorshException('Struct schema missing "fields" array.');
            }

            foreach ($fields as $entry) {
                if (! is_array($entry) || ! is_string($entry[0] ?? null)) {
                    throw new BorshException('Malformed struct field entry.');
                }

                [$fieldName, $fieldType] = $entry;

                self::serializeField(
                    schema: $schema,
                    value: $object->{$fieldName},
                    fieldType: $fieldType,
                    writer: $writer,
                );
            }

            return;
        }

        if ($kind === 'enum') {
            $discriminantField = $objectSchema['field'] ?? 'enum';
            if (! is_string($discriminantField)) {
                throw new BorshException('Enum schema "field" must be a string.');
            }

            $discriminant = $object->{$discriminantField} ?? null;
            if (! is_int($discriminant)) {
                $class = $object::class;
                throw new BorshException("Enum {$class} requires int property \"{$discriminantField}\" holding variant index.");
            }

            $variants = $objectSchema['values'] ?? [];
            if (! is_array($variants) || ! array_key_exists($discriminant, $variants)) {
                $class = $object::class;
                throw new BorshException("Enum {$class} has no variant at index {$discriminant}.");
            }

            $writer->writeU8($discriminant);

            $variant = $variants[$discriminant];
            if (! is_array($variant) || ! is_string($variant[0] ?? null)) {
                throw new BorshException('Malformed enum variant entry.');
            }

            [$variantName, $variantType] = $variant;
            if ($variantType !== null) {
                self::serializeField(
                    schema: $schema,
                    value: $object->{$variantName},
                    fieldType: $variantType,
                    writer: $writer,
                );
            }

            return;
        }

        $kindString = is_scalar($kind) ? (string) $kind : 'unknown';
        $class = $object::class;
        throw new BorshException("Unexpected schema kind: {$kindString} for {$class}");
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     */
    private static function serializeField(
        array        $schema,
        mixed        $value,
        mixed        $fieldType,
        BinaryWriter $writer,
    ): void {
        if (is_string($fieldType)) {
            self::serializePrimitive($fieldType, $value, $writer);

            return;
        }

        if (is_array($fieldType) && isset($fieldType[0])) {
            self::serializeListField($schema, $fieldType, $value, $writer);

            return;
        }

        if (is_array($fieldType) && isset($fieldType['kind'])) {
            /** @var array<string, mixed> $fieldType */
            self::serializeOptionField($schema, $fieldType, $value, $writer);

            return;
        }

        if (is_object($value)) {
            self::serializeObject(
                schema: $schema,
                object: $value,
                writer: $writer,
            );

            return;
        }

        $rendered = is_scalar($fieldType) ? (string) $fieldType : 'array';
        throw new BorshException("FieldType {$rendered} unrecognized");
    }

    private static function serializePrimitive(string $fieldType, mixed $value, BinaryWriter $writer): void
    {
        match ($fieldType) {
            'u8' => $writer->writeU8(self::int($value)),
            'u16' => $writer->writeU16(self::int($value)),
            'u32' => $writer->writeU32(self::int($value)),
            'u64' => $writer->writeU64(self::int($value)),
            'i8' => $writer->writeI8(self::int($value)),
            'i16' => $writer->writeI16(self::int($value)),
            'i32' => $writer->writeI32(self::int($value)),
            'i64' => $writer->writeI64(self::int($value)),
            'f32' => $writer->writeF32(self::float($value)),
            'f64' => $writer->writeF64(self::float($value)),
            'string' => $writer->writeString(self::string($value)),
            'pubkey', 'pubKey' => $writer->writeFixedArray(
                $value instanceof PublicKey ? $value->toBytes() : self::byteArray($value)
            ),
            'pubkeyAsString', 'pubKeyAsString' => $writer->writeFixedArray(
                PublicKey::from($value)->toBytes()
            ),
            default => throw new BorshException("FieldType {$fieldType} unrecognized"),
        };
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     * @param array<int|string, mixed> $fieldType
     */
    private static function serializeListField(array $schema, array $fieldType, mixed $value, BinaryWriter $writer): void
    {
        if (is_int($fieldType[0])) {
            $bytes = self::byteArray($value);

            if (count($bytes) !== $fieldType[0]) {
                $sizeOf = count($bytes);
                throw new BorshException("Expecting byte array of length {{$fieldType[0]}}, but got {{$sizeOf}} bytes");
            }

            $writer->writeFixedArray($bytes);

            return;
        }

        if (count($fieldType) === 2 && is_int($fieldType[1])) {
            if (! is_array($value) || count($value) !== $fieldType[1]) {
                $sizeOf = is_array($value) ? count($value) : 0;
                throw new BorshException("Expecting byte array of length {{$fieldType[1]}}, but got {{$sizeOf}} bytes");
            }

            for ($i = 0; $i < $fieldType[1]; $i++) {
                self::serializeField(
                    schema: $schema,
                    value: $value[$i],
                    fieldType: $fieldType[0],
                    writer: $writer,
                );
            }

            return;
        }

        if (! is_array($value)) {
            throw new BorshException('Dynamic-length Borsh array expects an array value.');
        }

        /** @var array<int, mixed> $items */
        $items = array_values($value);
        $writer->writeArray($items, static function (mixed $item) use ($schema, $fieldType, $writer): void {
            self::serializeField(
                schema: $schema,
                value: $item,
                fieldType: $fieldType[0],
                writer: $writer,
            );
        });
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     * @param array<string, mixed> $fieldType
     */
    private static function serializeOptionField(array $schema, array $fieldType, mixed $value, BinaryWriter $writer): void
    {
        $fieldKind = $fieldType['kind'] ?? null;

        if ($fieldKind !== 'option' && $fieldKind !== 'struct') {
            $rendered = is_scalar($fieldKind) ? (string) $fieldKind : 'unknown';
            throw new BorshException("FieldType {{$rendered}} unrecognized");
        }

        if ($value === null) {
            $writer->writeU8(0);

            return;
        }

        $writer->writeU8(1);

        self::serializeField(
            schema: $schema,
            value: $value,
            fieldType: $fieldType['type'] ?? null,
            writer: $writer,
        );
    }

    /**
     * @template TClass of object
     * @param array<class-string, array<string, mixed>> $schema
     * @param class-string<TClass> $class
     * @param Buffer|array<int, int> $buffer
     * @return TClass
     */
    public static function deserialize(array $schema, string $class, Buffer|array $buffer): object
    {
        $result = self::deserializeObject(
            schema: $schema,
            class: $class,
            reader: new BinaryReader(Buffer::from($buffer)),
        );

        /** @var TClass $result */
        return $result;
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     * @param class-string $class
     */
    private static function deserializeObject(array $schema, string $class, BinaryReader $reader): object
    {
        $objectSchema = $schema[$class] ?? null;
        if (! is_array($objectSchema)) {
            throw new BorshException("Class {$class} is missing in schema");
        }

        $kind = $objectSchema['kind'] ?? null;

        if ($kind === 'struct') {
            return self::deserializeStruct($schema, $class, $objectSchema, $reader);
        }

        if ($kind === 'enum') {
            return self::deserializeEnum($schema, $class, $objectSchema, $reader);
        }

        $kindString = is_scalar($kind) ? (string) $kind : 'unknown';
        throw new BorshException("Unexpected schema kind: {$kindString} for {$class}");
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     * @param class-string $class
     * @param array<string, mixed> $objectSchema
     */
    private static function deserializeStruct(array $schema, string $class, array $objectSchema, BinaryReader $reader): object
    {
        if (! method_exists($class, 'borshConstructor')) {
            throw new BorshException("Class {{$class}} does not implement borshConstructor. Please use the BorshDeserialize trait.");
        }

        $fields = $objectSchema['fields'] ?? [];
        if (! is_array($fields)) {
            throw new BorshException("Struct {$class} missing \"fields\" array.");
        }

        /** @var object $result */
        $result = $class::borshConstructor();

        foreach ($fields as $entry) {
            if (! is_array($entry) || ! is_string($entry[0] ?? null)) {
                throw new BorshException("Malformed struct field entry in {$class}.");
            }

            [$fieldName, $fieldType] = $entry;
            $result->{$fieldName} = self::deserializeField(
                schema: $schema,
                fieldType: $fieldType,
                reader: $reader,
            );
        }

        return $result;
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     * @param class-string $class
     * @param array<string, mixed> $objectSchema
     */
    private static function deserializeEnum(array $schema, string $class, array $objectSchema, BinaryReader $reader): object
    {
        if (! method_exists($class, 'borshConstructor')) {
            throw new BorshException("Class {$class} does not implement borshConstructor. Please use the BorshDeserialize trait.");
        }

        $discriminant = $reader->readU8();
        $variants = $objectSchema['values'] ?? [];

        if (! is_array($variants) || ! array_key_exists($discriminant, $variants)) {
            throw new BorshException("Enum {$class} has no variant at index {$discriminant}.");
        }

        $variant = $variants[$discriminant];
        if (! is_array($variant) || ! is_string($variant[0] ?? null)) {
            throw new BorshException("Malformed enum variant entry in {$class}.");
        }

        $discriminantField = $objectSchema['field'] ?? 'enum';
        if (! is_string($discriminantField)) {
            throw new BorshException("Enum {$class} schema \"field\" must be a string.");
        }

        [$variantName, $variantType] = $variant;

        /** @var object $result */
        $result = $class::borshConstructor();
        $result->{$discriminantField} = $discriminant;
        if ($variantType !== null) {
            $result->{$variantName} = self::deserializeField(
                schema: $schema,
                fieldType: $variantType,
                reader: $reader,
            );
        }

        return $result;
    }

    /**
     * @param array<class-string, array<string, mixed>> $schema
     */
    private static function deserializeField(array $schema, mixed $fieldType, BinaryReader $reader): mixed
    {
        if (is_string($fieldType) && ! class_exists($fieldType)) {
            return match ($fieldType) {
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
                'string' => $reader->readString(),
                'pubkey', 'pubKey' => $reader->readPubKey(),
                'pubkeyAsString', 'pubKeyAsString' => $reader->readPubKeyAsString(),
                default => throw new BorshException("FieldType {{$fieldType}} unrecognized"),
            };
        }

        if (is_array($fieldType) && isset($fieldType[0])) {
            if (is_int($fieldType[0])) {
                return $reader->readFixedArray($fieldType[0]);
            }

            if (count($fieldType) === 2 && is_int($fieldType[1])) {
                $array = [];
                for ($i = 0; $i < $fieldType[1]; $i++) {
                    $array[] = self::deserializeField(
                        schema: $schema,
                        fieldType: $fieldType[0],
                        reader: $reader,
                    );
                }

                return $array;
            }

            return $reader->readArray(fn (): mixed => self::deserializeField(
                schema: $schema,
                fieldType: $fieldType[0],
                reader: $reader,
            ));
        }

        if (is_array($fieldType) && isset($fieldType['kind']) && $fieldType['kind'] === 'option') {
            $option = $reader->readU8();
            if ($option !== 0) {
                return self::deserializeField(
                    schema: $schema,
                    fieldType: $fieldType['type'] ?? null,
                    reader: $reader,
                );
            }

            return null;
        }

        if (is_string($fieldType) && class_exists($fieldType)) {
            return self::deserializeObject(
                schema: $schema,
                class: $fieldType,
                reader: $reader,
            );
        }

        throw new BorshException('Borsh deserializeField received an unrecognized field type.');
    }

    private static function int(mixed $value): int
    {
        if (! is_int($value)) {
            throw new BorshException('Expected integer value for Borsh primitive.');
        }

        return $value;
    }

    private static function float(mixed $value): float
    {
        if (! is_float($value) && ! is_int($value)) {
            throw new BorshException('Expected float value for Borsh primitive.');
        }

        return (float) $value;
    }

    private static function string(mixed $value): string
    {
        if (! is_string($value)) {
            throw new BorshException('Expected string value for Borsh primitive.');
        }

        return $value;
    }

    /**
     * @return array<int, int>
     */
    private static function byteArray(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $byte) {
                if (! is_int($byte)) {
                    throw new BorshException('Expected integer bytes in byte array.');
                }

                $out[] = $byte;
            }

            return $out;
        }

        if ($value instanceof Buffer) {
            return $value->toArray();
        }

        if ($value instanceof PublicKey) {
            return $value->toBytes();
        }

        throw new BorshException('Expected byte array for Borsh field.');
    }
}
