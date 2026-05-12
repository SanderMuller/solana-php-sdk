<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Util;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Util\Commitment;
use PHPUnit\Framework\Attributes\Test;

final class CommitmentTest extends TestCase
{
    #[Test]
    public function factory_methods_produce_each_level(): void
    {
        self::assertSame(Commitment::FINALIZED, Commitment::finalized()->commitmentLevel);
        self::assertSame(Commitment::CONFIRMED, Commitment::confirmed()->commitmentLevel);
        self::assertSame(Commitment::PROCESSED, Commitment::processed()->commitmentLevel);
    }

    #[Test]
    public function string_cast_returns_level(): void
    {
        self::assertSame('finalized', Commitment::finalized()->toString());
        self::assertSame('confirmed', (string) Commitment::confirmed());
    }

    #[Test]
    public function rejects_unknown_level(): void
    {
        $this->expectException(InputValidationException::class);

        new Commitment('partial');
    }
}
