<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

use ArrayAccess;
use Collectiq\SolanaPhpSdk\Exceptions\NotImplementedException;
use Collectiq\SolanaPhpSdk\Util\Buffer;

final class Borsh
{
    public static function serialize(
        array  $schema,
        $object,
    ): array {
        $writer = new BinaryWriter();

        self::serializeObject(
            schema: $schema,
            object: $object,
            writer: $writer,
        );

        return $writer->toArray();
    }

    private static function serializeObject(
        array        $schema,
              $object,
        BinaryWriter $writer
    ): void {
        $objectSchema = $schema[$object::class] ?? null;
        if (! $objectSchema) {
            $class = $object::class;
            throw new BorshException("Class {$class} is missing in schema");
        }

        if ($objectSchema['kind'] === 'struct') {
            foreach ($objectSchema['fields'] as [$fieldName, $fieldType]) {
                self::serializeField(
                    schema: $schema,
                    value: $object->{$fieldName},
                    fieldType: $fieldType,
                    writer: $writer,
                );
            }
        } elseif ($objectSchema['kind'] === 'enum') {
            dd($objectSchema);
            throw new NotImplementedException("TODO: Enums don't exist in PHP yet???");
        } else {
            $kind = $objectSchema['kind'];
            $class = $object::class;
            throw new BorshException("Unexpected schema kind: {$kind} for {$class}");
        }
    }

    private static function serializeField(
        array                    $schema,
        mixed                    $value,
        string|array|ArrayAccess $fieldType,
        BinaryWriter             $writer,
    ): void {
        if (is_string($fieldType)) {
            match ($fieldType) {
                'u8' => $writer->writeU8($value),
                'u16' => $writer->writeU16($value),
                'u32' => $writer->writeU32($value),
                'u64' => $writer->writeU64($value),
                'i8' => $writer->writeI8($value),
                'i16' => $writer->writeI16($value),
                'i32' => $writer->writeI32($value),
                'i64' => $writer->writeI64($value),
                'f32' => $writer->writeF32($value),
                'f64' => $writer->writeF64($value),
                'string' => $writer->writeString($value),
                default => throw new BorshException("FieldType {$fieldType} unrecognized"),
            };

            return;
        }

        if (is_array($fieldType) && isset($fieldType[0])) { // list
            if (is_int($fieldType[0])) {
                if (count($value) !== $fieldType[0]) {
                    $sizeOf = count($value);
                    throw new BorshException("Expecting byte array of length {{$fieldType[0]}}, but got {{$sizeOf}} bytes");
                }

                $writer->writeFixedArray($value);
            } elseif (count($fieldType) === 2 && is_int($fieldType[1])) {
                if (count($value) !== $fieldType[1]) {
                    $sizeOf = count($value);
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
            } else {
                $writer->writeArray($value, function (mixed $item) use ($schema, $fieldType, $writer): void {
                    self::serializeField(
                        schema: $schema,
                        value: $item,
                        fieldType: $fieldType[0],
                        writer: $writer,
                    );
                });
            }

            return;
        }

        if (isset($fieldType['kind'])) { // associative array
            $fieldKind = $fieldType['kind'];

            if ($fieldKind !== 'option' && $fieldKind !== 'struct') {
                throw new BorshException("FieldType {{$fieldType['kind']}} unrecognized");
            }

            if (! $value) {
                $writer->writeU8(0);

                return;
            }

            $writer->writeU8(1);

            self::serializeField(
                schema: $schema,
                value: $value,
                fieldType: $fieldType['type'],
                writer: $writer,
            );

            return;
        }

        self::serializeObject(
            schema: $schema,
            object: $value,
            writer: $writer,
        );
    }

    /**
     * @template TClass of BorshSerializable
     */
    public static function deserialize(
        array        $schema,
        /** @var class-string<TClass> $class */
        string       $class,
        Buffer|array $buffer,
    ): mixed {
        return self::deserializeObject(
            schema: $schema,
            class: $class,
            reader: new BinaryReader(Buffer::from($buffer)),
        );
    }

    /**
     * @template TClass of BorshSerializable
     * @return TClass
     */
    private static function deserializeObject(
        array        $schema,
        /** @var class-string<TClass> $class */
        string       $class,
        BinaryReader $reader,
    ): ?BorshSerializable {
        $objectSchema = $schema[$class] ?? null;
        if (! $objectSchema) {
            throw new BorshException("Class {$class} is missing in schema");
        }

        if ($objectSchema['kind'] === 'struct') {
            if (! method_exists($class, 'borshConstructor')) {
                throw new BorshException("Class {{$class}} does not implement borshConstructor. Please use the BorshDeserialize trait.");
            }

            $result = $class::borshConstructor();
            foreach ($objectSchema['fields'] as [$fieldName, $fieldType]) {
                $result->{$fieldName} = self::deserializeField(
                    schema: $schema,
                    fieldType: $fieldType,
                    reader: $reader,
                );
                // $result->fields[$fieldName] = static::deserializeField($schema, $fieldName, $fieldType, $reader);
            }

            return $result;
        }

        if ($objectSchema['kind'] === 'enum') {
            throw new NotImplementedException();
        }

        throw new BorshException("Unexpected schema kind: {$objectSchema['kind']} for {$class}");
    }

    private static function deserializeField(
        array        $schema,
        string|array $fieldType,
        BinaryReader $reader,
    ): mixed {
        if (is_string($fieldType) && ! class_exists($fieldType)) {
            return match($fieldType) {
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
                default => throw new BorshException("FieldType {{$fieldType}} unrecognized"),
            };
        }

        if (is_array($fieldType) && isset($fieldType[0])) {
            // sequential array
            if (is_int($fieldType[0])) {
                return $reader->readFixedArray($fieldType[0]);
            }

            // sequential array
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

        if (isset($fieldType['kind']) && $fieldType['kind'] === 'option') { // associative array
            $option = $reader->readU8();
            if ($option !== 0) {
                return self::deserializeField(
                    schema: $schema,
                    fieldType: $fieldType['type'],
                    reader: $reader,
                );
            }

            return null;
        }

        return self::deserializeObject(
            schema: $schema,
            class: $fieldType,
            reader: $reader,
        );
    }
}
