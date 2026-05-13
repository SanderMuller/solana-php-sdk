<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Util;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SplTokenProgram;
use SanderMuller\SolanaPhpSdk\Programs\Token2022Program;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Ata;
use SanderMuller\SolanaPhpSdk\Util\Pda;

/** @internal */
final class PdaAtaTest extends TestCase
{
    #[Test]
    public function pda_find_returns_address_and_bump(): void
    {
        $programId = PublicKey::from(SplTokenProgram::TOKEN_PROGRAM_ID);
        [$address, $bump] = Pda::find($programId, ['seed-one', 'seed-two']);

        self::assertInstanceOf(PublicKey::class, $address);
        self::assertGreaterThanOrEqual(0, $bump);
        self::assertLessThan(256, $bump);

        // The lone address() helper agrees with the indexed shape.
        self::assertSame($address->toBase58(), Pda::address($programId, ['seed-one', 'seed-two'])->toBase58());
    }

    #[Test]
    public function ata_derive_matches_legacy_spl_token_helper(): void
    {
        $owner = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();

        $expected = (new SplTokenProgram())->getAssociatedTokenAddressSync($mint, $owner);
        $actual = Ata::derive($owner, $mint);

        self::assertSame($expected->toBase58(), $actual->toBase58());
    }

    #[Test]
    public function ata_derive_2022_differs_from_legacy(): void
    {
        $owner = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();

        $legacy = Ata::derive($owner, $mint);
        $token2022 = Ata::derive2022($owner, $mint);

        self::assertNotSame($legacy->toBase58(), $token2022->toBase58());

        // Token-2022 helper must equal explicit form.
        $explicit = Ata::derive($owner, $mint, Token2022Program::TOKEN_PROGRAM_ID);
        self::assertSame($token2022->toBase58(), $explicit->toBase58());
    }
}
