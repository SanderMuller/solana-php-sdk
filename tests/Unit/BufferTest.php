<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use PHPUnit\Framework\Attributes\Test;

final class BufferTest extends TestCase
{
    #[Test]
    public function ibuffer_push_fixed_length(): void
    {
        $lamports = 4;
        $space = 6;
        $programId = Keypair::generate()->getPublicKey();

        $rawCreateAccountBinary = [
            // uint32
            ...unpack('C*', pack('V', SystemProgram::PROGRAM_INDEX_CREATE_ACCOUNT)),
            // int64
            ...unpack('C*', pack('P', $lamports)),
            // int64
            ...unpack('C*', pack('P', $space)),
            //
            ...$programId->toBytes(),
        ];

        $bufferable = Buffer::empty()
            ->push(
                Buffer::fromInt(SystemProgram::PROGRAM_INDEX_CREATE_ACCOUNT, BufferType::INT, false)
            )
            ->push(
                Buffer::fromInt($lamports, BufferType::LONG, false)
            )
            ->push(
                Buffer::fromInt($space, BufferType::LONG, false)
            )
            ->push($programId);

        $this->assertEquals($rawCreateAccountBinary, $bufferable->toArray());
    }

    #[Test]
    public function concat(): void
    {
        $buffer = Buffer::concat(
            Buffer::fromInt(1, BufferType::INT, false),
            Buffer::fromInt(2, BufferType::INT, false),
            Buffer::fromInt(3, BufferType::INT, false),
        );

        $this->assertSame([1, 0, 0, 0, 2, 0, 0, 0, 3, 0, 0, 0], $buffer->toArray());
    }

    #[Test]
    public function fromArray(): void
    {
        $buffer = Buffer::fromArray([1, 2, 3, 4]);

        $this->assertSame([1, 2, 3, 4], $buffer->toArray());
    }
}
