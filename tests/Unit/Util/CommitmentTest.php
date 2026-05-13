<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Util;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Util\Commitment;

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
