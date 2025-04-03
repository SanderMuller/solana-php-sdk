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
 */
class Buffer implements Arrayable, Countable, Stringable
{
    protected static bool $defaultsToBase58 = false;

    public static ?int $fixedLength = null;

    final public function __construct(
        /**
         * @var array<int>
         */
        private array                $data,
        private readonly ?BufferType $datatype = null,
        private readonly ?bool       $isDatatypeSigned = null,
    ) {
        $expectedLength = static::$fixedLength;

        if ($expectedLength !== null && $this->length() !== $expectedLength) {
            throw new InputValidationException("Invalid input. Expected length {$expectedLength}. Found: {$this->length()}");
        }
    }

    final public static function fromArray(Arrayable|iterable $value, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        return new static(
            data: (array) $value,
            datatype: $datatype,
            isDatatypeSigned: $isSignedValue,
        );
    }

    final public static function fromString(string|Stringable $value, ?BufferType $datatype = null, ?bool $isSignedValue = null): static
    {
        $datatype ??= BufferType::STRING;

        if ($value instanceof Stringable) {
            $value = $value->__toString();
        }

        if (is_numeric($value) && $datatype !== BufferType::STRING) {
            $value = pack(
                static::computedFormat($datatype, $isSignedValue)->value,
                $value,
            );
        }

        if (static::$defaultsToBase58) {
            $isBinaryString = preg_match('~[^\x20-\x7E\t\r\n]~', $value) > 0;

            // if not binary string already, assumed to be a base58 string.
            if (! $isBinaryString) {
                return static::fromBase58($value);
            }
        }

        return new static(
            data: array_values(unpack('C*', $value)),
            datatype: $datatype,
            isDatatypeSigned: $isSignedValue,
        );
    }

    final public static function fromInt(int $value, BufferType $datatype, ?bool $isSignedValue = null): static
    {
        if (static::$defaultsToBase58) {
            return static::empty()->pad(PublicKey::$fixedLength, $value);
        }

        return static::fromString((string) $value, $datatype, $isSignedValue);
    }

    final public static function fromFloat(float $value, BufferType $datatype, ?bool $isSignedValue = null): static
    {
        return static::fromString((string) $value, $datatype, $isSignedValue);
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
            return static::empty(
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if ($value instanceof PublicKey) {
            $value = $value->getBuffer();
        }

        if ($value instanceof self) {
            return static::fromBuffer(
                value: $value,
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if ($value instanceof Arrayable || (is_object($value) && method_exists($value, 'toArray'))) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
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

            return static::fromInt(
                value: $value,
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if (is_float($value)) {
            if (! $datatype instanceof BufferType) {
                throw new InputValidationException('datatype is required for values of type float');
            }

            return static::fromFloat(
                value: $value,
                datatype: $datatype,
                isSignedValue: $isSignedValue,
            );
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            return static::fromString(
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
        return static::fromString(
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
            : static::from($source);

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

        $this->data = $fixedSizeData->toArray();

        return $this;
    }

    /**
     * Return binary representation of $value.
     */
    final public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Return the byte array representation of the public key
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
            format: static::computedFormat($this->datatype, $this->isDatatypeSigned)->value,
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

        return match ($dataType) {
            BufferType::STRING => BufferFormat::CHAR_UNSIGNED,
            BufferType::BYTE => $isSignedValue ? BufferFormat::CHAR_SIGNED : BufferFormat::CHAR_UNSIGNED,
            BufferType::SHORT => $isSignedValue ? BufferFormat::SHORT_16_SIGNED : BufferFormat::SHORT_16_UNSIGNED,
            BufferType::INT => $isSignedValue ? BufferFormat::LONG_32_SIGNED : BufferFormat::LONG_32_UNSIGNED,
            BufferType::LONG => $isSignedValue ? BufferFormat::LONG_LONG_64_SIGNED : BufferFormat::LONG_LONG_64_UNSIGNED,
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
