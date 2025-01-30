<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

use Closure;
use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Util\Buffer;

final class BinaryReader
{
    private int $offset = 0;

    public function __construct(private readonly Buffer $buffer) {}

    public function readU8(): int
    {
        return $this->readUnsignedInt(1, BufferType::BYTE);
    }

    public function readU16(): int
    {
        return $this->readUnsignedInt(2, BufferType::SHORT);
    }

    public function readU32(): int
    {
        return $this->readUnsignedInt(4, BufferType::INT);
    }

    public function readU64(): int
    {
        return $this->readUnsignedInt(8, BufferType::LONG);
    }

    public function readI8(): int
    {
        return $this->readSignedInt(1, BufferType::BYTE);
    }

    public function readI16(): int
    {
        return $this->readSignedInt(2, BufferType::SHORT);
    }

    public function readI32(): int
    {
        return $this->readSignedInt(4, BufferType::INT);
    }

    public function readI64(): int
    {
        return $this->readSignedInt(8, BufferType::LONG);
    }

    private function readUnsignedInt(?int $length, ?BufferType $datatype): int
    {
        $value = $this->buffer->slice($this->offset, $length, $datatype, false)->value();
        $this->offset += $length;

        return $value;
    }

    private function readSignedInt(?int $length, ?BufferType $datatype): int
    {
        $value = $this->buffer->slice($this->offset, $length, $datatype, true)->value();
        $this->offset += $length;

        return $value;
    }

    public function readF32(): float
    {
        $value = $this->buffer->slice($this->offset, 4, BufferType::FLOAT, true)->value();
        $this->offset += 4;

        return $value;
    }

    public function readF64(): float
    {
        $value = $this->buffer->slice($this->offset, 8, BufferType::FLOAT, true)->value();
        $this->offset += 8;

        return $value;
    }

    /**
     * @throws BorshException
     */
    public function readString(): string
    {
        $length = $this->readU32();

        return $this->readBuffer($length)->toString();
    }

    public function readFixedArray(int $length): array
    {
        return $this->readBuffer($length)->toArray();
    }

    public function readPubKey(): PublicKey
    {
        return PublicKey::from($this->readFixedArray(32));
    }

    public function readPubKeyAsString(): string
    {
        return $this->readPubKey()->toBase58();
    }

    public function readArray(Closure $readEachItem): array
    {
        $length = $this->readU32();
        $array = [];
        for ($i = 0; $i < $length; $i++) {
            $array[] = $readEachItem();
        }

        return $array;
    }

    /**
     * @throws BorshException
     */
    private function readBuffer(int $length): Buffer
    {
        if ($this->offset + $length > $this->buffer->length()) {
            throw new BorshException("Expected buffer length {$length} isn't within bounds");
        }

        $buffer = $this->buffer->slice($this->offset, $length);
        $this->offset += $length;

        return $buffer;
    }
}
