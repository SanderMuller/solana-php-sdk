<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Enum\Buffer\BufferType;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

final class BufferTest extends TestCase
{
    #[Test]
    public function ibuffer_push_fixed_length(): void
    {
        $lamports = 4;
        $space = 6;
        $programId = Keypair::generate()->getPublicKey();

        $u32 = unpack('C*', pack('V', SystemProgram::PROGRAM_INDEX_CREATE_ACCOUNT));
        $lamportsBytes = unpack('C*', pack('P', $lamports));
        $spaceBytes = unpack('C*', pack('P', $space));
        assert(is_array($u32) && is_array($lamportsBytes) && is_array($spaceBytes));

        $rawCreateAccountBinary = [
            ...$u32,
            ...$lamportsBytes,
            ...$spaceBytes,
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
            ->push($programId->getBuffer());

        self::assertEquals($rawCreateAccountBinary, $bufferable->toArray());
    }

    #[Test]
    public function concat(): void
    {
        $buffer = Buffer::concat(
            Buffer::fromInt(1, BufferType::INT, false),
            Buffer::fromInt(2, BufferType::INT, false),
            Buffer::fromInt(3, BufferType::INT, false),
        );

        self::assertSame([1, 0, 0, 0, 2, 0, 0, 0, 3, 0, 0, 0], $buffer->toArray());
    }

    #[Test]
    public function from_array(): void
    {
        $buffer = Buffer::fromArray([1, 2, 3, 4]);

        self::assertSame([1, 2, 3, 4], $buffer->toArray());
    }
}
