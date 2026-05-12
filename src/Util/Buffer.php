<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferFormat;
use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\PublicKey;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use SplFixedArray;
use StephenHill\Base58;
use Stringable;

/**
 * Convenience wrapper class around an array of bytes (integers).
 *
 * @implements Arrayable<int, int>
 */
class Buffer implements Arrayable, Countable, Stringable
{
    protected static bool $defaultsToBase58 = false;

    public static ?int $fixedLength = null;

    /**
     * @param array<int, int> $data
     */
    final public function __construct(
        private array                $data,
        private readonly ?BufferType $datatype = null,
        private readonly ?bool       $isDatatypeSigned = null,
    ) {
        $expectedLength = static::$fixedLength;

        if ($expectedLength !== null && $this->length() !== $expectedLength) {
            throw new InputValidationException("Invalid input. Expected length {$expectedLength}. Found: {$this->length()}");
        }
    }

    /**
     * @param Arrayable<int|string, int>|iterable<int, int> $value
     */
    final public static function fromArray(Arrayable|iterable $value, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            $value = iterator_to_array($value, false);
        }

        /** @var array<int, int> $value */
        return new static(
            data: $value,
            datatype: $datatype,
            isDatatypeSigned: $isSignedValue,
        );
    }

    final public static function fromString(string|Stringable $value, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        if ($value instanceof Stringable) {
            $value = $value->__toString();
        }

        if (is_numeric($value) && $datatype instanceof BufferType && $datatype !== BufferType::STRING) {
            $packed = pack(
                self::computedFormat($datatype, $isSignedValue)->value,
                $value,
            );
            $value = $packed;
        }

        if (static::$defaultsToBase58) {
            $isBinaryString = preg_match('~[^\x20-\x7E\t\r\n]~', $value) > 0;

            // if not binary string already, assumed to be a base58 string.
            if (! $isBinaryString) {
                return self::fromBase58($value);
            }
        }

        $unpacked = unpack('C*', $value);
        /** @var array<int, int> $data */
        $data = $unpacked === false ? [] : array_values($unpacked);

        return new static(
            data: $data,
            datatype: $datatype,
            isDatatypeSigned: $isSignedValue,
        );
    }

    final public static function fromInt(int $value, BufferType $datatype, ?bool $isSignedValue = null): static
    {
        if (static::$defaultsToBase58) {
            return self::empty()->pad(PublicKey::$fixedLength ?? 32, $value);
        }

        return self::fromString((string) $value, $datatype, $isSignedValue);
    }

    final public static function fromFloat(float $value, BufferType $datatype, ?bool $isSignedValue = null): static
    {
        return self::fromString((string) $value, $datatype, $isSignedValue);
    }

    final public static function fromBuffer(self $value, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        return new static(
            data: $value->toArray(),
            datatype: $datatype ?? $value->datatype,
            isDatatypeSigned: $isSignedValue ?? $value->isDatatypeSigned,
        );
    }

    final public static function from(mixed $value, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        if ($value === null) {
            return self::empty(
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if ($value instanceof PublicKey) {
            $value = $value->getBuffer();
        }

        if ($value instanceof self) {
            return self::fromBuffer(
                value: $value,
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if ($value instanceof Arrayable || (is_object($value) && method_exists($value, 'toArray'))) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            /** @var array<int, int> $value */
            return new static(
                data: $value,
                datatype: $datatype,
                isDatatypeSigned: $isSignedValue,
            );
        }

        if (is_int($value)) {
            if (! $datatype instanceof BufferType) {
                throw new InputValidationException('datatype is required for values of type int');
            }

            return self::fromInt(
                value: $value,
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if (is_float($value)) {
            if (! $datatype instanceof BufferType) {
                throw new InputValidationException('datatype is required for values of type float');
            }

            return self::fromFloat(
                value: $value,
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            return self::fromString(
                value: $value,
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        $bufferType = gettype($value);

        throw new InputValidationException("Unsupported value for Buffer of type {$bufferType}");
    }

    final public static function concat(self ...$buffers): static
    {
        $data = [];

        foreach ($buffers as $buffer) {
            $data = [
                ...$data,
                ...$buffer->toArray(),
            ];
        }

        return new static(
            data: $data,
        );
    }

    final public static function fromBase58(string $value, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        return self::fromString(
            value: new Base58()->decode($value),
            datatype: $datatype,
            isSignedValue: $isSignedValue,
        );
    }

    final public function pad(int $len, int $val = 0): static
    {
        $this->data = array_pad($this->data, $len, $val);

        return $this;
    }

    final public function push(mixed $source): static
    {
        $sourceAsBuffer = $source instanceof self
            ? $source
            : self::from($source);

        //        array_push($this->data, ...$sourceAsBuffer->toArray());
        //
        $this->data = [
            ...$this->data,
            ...$sourceAsBuffer->toArray(),
        ];

        return $this;
    }

    final public function slice(int $offset, ?int $length = null, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        return new static(
            data: array_slice($this->data, $offset, $length),
            datatype: $datatype,
            isDatatypeSigned: $isSignedValue,
        );
    }

    final public function splice(int $offset, ?int $length = null, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        return new static(
            data: array_splice($this->data, $offset, $length),
            datatype: $datatype,
            isDatatypeSigned: $isSignedValue,
        );
    }

    final public function shift(): ?int
    {
        return array_shift($this->data);
    }

    final public function fixed(int $size): static
    {
        $fixedSizeData = SplFixedArray::fromArray($this->data);
        $fixedSizeData->setSize($size);

        /** @var array<int, int> $resized */
        $resized = $fixedSizeData->toArray();
        $this->data = $resized;

        return $this;
    }

    /**
     * Return binary representation of $value.
     *
     * @return array<int, int>
     */
    final public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Return the byte array representation of the public key
     *
     * @return array<int, int>
     */
    final public function toBytes(): array
    {
        return $this->toArray();
    }

    /**
     * Return binary string representation of $value.
     */
    final public function toString(): string
    {
        $value = $this->toBinaryString();

        if (static::$defaultsToBase58) {
            return new Base58()->encode($value);
        }

        return $value;
    }

    final public function toBinaryString(): string
    {
        return pack('C*', ...$this->toArray());
    }

    final public function toBase58String(): string
    {
        return new Base58()->encode($this->toString());
    }

    final public function length(): int
    {
        return $this->count();
    }

    /**
     * Check if two publicKeys are equal
     */
    final public function equals(self $buffer): bool
    {
        return $buffer->toBinaryString() === $this->toBinaryString();
    }

    final public function count(): int
    {
        return count($this->data);
    }

    final public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Convert the binary array to its corresponding value derived from $datatype, $signed, and sizeof($data).
     *
     * Note: it is expected that the ->fixed($length) method has already been called.
     */
    final public function value(?int $length = null): mixed
    {
        if ($length !== null) {
            $this->fixed($length);
        }

        if ($this->datatype === BufferType::STRING) {
            return ord($this->toBinaryString());
        }

        $unpacked = unpack(
            format: self::computedFormat($this->datatype, $this->isDatatypeSigned)->value,
            string: $this->toBinaryString(),
        );

        if ($unpacked === false) {
            throw new InputValidationException('Failed to unpack buffer.');
        }

        return $unpacked[1];
    }

    final protected static function computedFormat(?BufferType $dataType, ?bool $isSignedValue): BufferFormat
    {
        if (! $dataType instanceof BufferType) {
            throw new InputValidationException('Trying to calculate format of unspecified buffer. Please specify a datatype.');
        }

        $signed = $isSignedValue === true;

        return match ($dataType) {
            BufferType::STRING => BufferFormat::CHAR_UNSIGNED,
            BufferType::BYTE => $signed ? BufferFormat::CHAR_SIGNED : BufferFormat::CHAR_UNSIGNED,
            BufferType::SHORT => $signed ? BufferFormat::SHORT_16_SIGNED : BufferFormat::SHORT_16_UNSIGNED,
            BufferType::INT => $signed ? BufferFormat::LONG_32_SIGNED : BufferFormat::LONG_32_UNSIGNED,
            BufferType::LONG => $signed ? BufferFormat::LONG_LONG_64_SIGNED : BufferFormat::LONG_LONG_64_UNSIGNED,
            BufferType::FLOAT => BufferFormat::FLOAT,
        };
    }

    final public static function empty(?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        return new static(
            data: [],
            datatype: $datatype,
            isDatatypeSigned: $isSignedValue,
        );
    }

    final public static function alloc(int $size): static
    {
        return new static(
            data: array_fill(0, $size, 0),
        );
    }
}
