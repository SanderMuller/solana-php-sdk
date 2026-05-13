<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\DataObjects;

use BadMethodCallException;
use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\DataObjects\BlockhashInfo;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class BlockhashInfoTest extends TestCase
{
    #[Test]
    public function from_value_extracts_fields_with_safe_fallbacks(): void
    {
        $bh = BlockhashInfo::fromValue([
            'blockhash' => '11111111111111111111111111111111',
            'lastValidBlockHeight' => 150,
        ]);

        self::assertSame('11111111111111111111111111111111', $bh->blockhash);
        self::assertSame(150, $bh->lastValidBlockHeight);
        self::assertFalse($bh->isEmpty());

        $empty = BlockhashInfo::fromValue([]);
        self::assertTrue($empty->isEmpty());
        self::assertNull($empty->lastValidBlockHeight);
    }

    #[Test]
    public function array_access_keeps_legacy_call_sites_working(): void
    {
        $bh = new BlockhashInfo('11111111111111111111111111111111', 200);

        self::assertSame('11111111111111111111111111111111', $bh['blockhash']); // @phpstan-ignore typePerfect.noArrayAccessOnObject
        self::assertSame(200, $bh['lastValidBlockHeight']); // @phpstan-ignore typePerfect.noArrayAccessOnObject
        self::assertTrue($bh->offsetExists('blockhash'));
        self::assertFalse($bh->offsetExists('unknown'));
    }

    #[Test]
    public function array_access_is_read_only(): void
    {
        $bh = new BlockhashInfo('11111111111111111111111111111111', 200);

        $this->expectException(BadMethodCallException::class);
        $bh['blockhash'] = 'mutated'; // @phpstan-ignore typePerfect.noArrayAccessOnObject
    }

    #[Test]
    public function connection_typed_accessor_returns_dto(): void
    {
        $this->fakeRpcByMethod([
            'getLatestBlockhash' => [
                'context' => ['slot' => 1],
                'value' => [
                    'blockhash' => '11111111111111111111111111111111',
                    'lastValidBlockHeight' => 300,
                ],
            ],
        ]);

        $bh = $this->container->get(Connection::class)->latestBlockhash();

        self::assertInstanceOf(BlockhashInfo::class, $bh);
        self::assertSame(300, $bh->lastValidBlockHeight);
    }
}
