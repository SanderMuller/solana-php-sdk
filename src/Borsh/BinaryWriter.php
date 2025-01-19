<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

use Closure;
use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\Util\Buffer;

final class BinaryWriter
{
    private readonly Buffer $buffer;

    private int $length = 0;

    public function __construct()
    {
        $this->buffer = Buffer::empty();
    }

    /**
     * @return $this
     */
    public function writeU8(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::BYTE, false)
        );
    }

    /**
     * @return $this
     */
    public function writeU16(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::SHORT, false)
        );
    }

    /**
     * @return $this
     */
    public function writeU32(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::INT, false)
        );
    }

    /**
     * @return $this
     */
    public function writeU64(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::LONG, false)
        );
    }

    /**
     * @return $this
     */
    public function writeI8(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::BYTE, true)
        );
    }

    /**
     * @return $this
     */
    public function writeI16(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::SHORT, true)
        );
    }

    /**
     * @return $this
     */
    public function writeI32(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::INT, true)
        );
    }

    /**
     * @return $this
     */
    public function writeI64(int $value): self
    {
        return $this->writeBuffer(
            Buffer::fromInt($value, BufferType::LONG, true)
        );
    }

    /**
     * @return $this
     */
    public function writeF32(float $value): self
    {
        return $this->writeBuffer(
            Buffer::fromFloat($value, BufferType::FLOAT, true)
                ->fixed(4)
        );
    }

    /**
     * @return $this
     */
    public function writeF64(float $value): self
    {
        return $this->writeBuffer(
            Buffer::fromFloat($value, BufferType::FLOAT, true)
                ->fixed(8)
        );
    }

    /**
     * @return $this
     */
    public function writeString(string $value): self
    {
        $valueBuffer = Buffer::fromString($value, BufferType::STRING);

        return $this
            ->writeU32($valueBuffer->length())
            ->writeBuffer($valueBuffer);
    }

    /**
     * @return $this
     */
    public function writeFixedArray(array $array): self
    {
        return $this->writeBuffer(Buffer::fromArray($array));
    }

    /**
     * @return $this
     */
    public function writeArray(array $array, Closure $writeFn): self
    {
        $this->writeU32(count($array));

        foreach ($array as $item) {
            $writeFn($item);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function writeBuffer(Buffer $buffer): self
    {
        $this->buffer->push($buffer);

        $this->length += $this->buffer->length();

        return $this;
    }

    public function toArray(): array
    {
        return $this->buffer
            ->slice(0, $this->length)
            ->toArray();
    }
}
