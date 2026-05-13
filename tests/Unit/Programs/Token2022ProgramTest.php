<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SplTokenProgram;
use SanderMuller\SolanaPhpSdk\Programs\Token2022Program;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class Token2022ProgramTest extends TestCase
{
    #[Test]
    public function transfer_checked_targets_token_2022_program_id(): void
    {
        $program = new Token2022Program();

        $source = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();
        $dest = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $ix = $program->createTransferCheckedInstruction(
            source: $source,
            mint: $mint,
            destination: $dest,
            owner: $owner,
            amount: 1_000,
            decimals: 6,
        );

        self::assertSame(Token2022Program::TOKEN_PROGRAM_ID, $ix->programId->toBase58());
        self::assertNotSame(SplTokenProgram::TOKEN_PROGRAM_ID, $ix->programId->toBase58());
    }

    #[Test]
    public function transfer_checked_data_layout_matches_legacy_token_program(): void
    {
        // Token-2022 inherits discriminators from legacy SPL Token; only the
        // program id differs. The instruction byte payload MUST be byte-for-
        // byte identical, otherwise downstream indexers that parse the data
        // generically will misread Token-2022 transfers.
        $legacy = new SplTokenProgram();
        $extended = new Token2022Program();

        $source = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();
        $dest = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $legacyIx = $legacy->createTransferCheckedInstruction($source, $mint, $dest, $owner, 1_000, 6);
        $extendedIx = $extended->createTransferCheckedInstruction($source, $mint, $dest, $owner, 1_000, 6);

        self::assertSame($legacyIx->data->toArray(), $extendedIx->data->toArray());
    }

    #[Test]
    public function ata_address_differs_between_legacy_and_token_2022(): void
    {
        $legacy = new SplTokenProgram();
        $extended = new Token2022Program();

        $owner = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();

        $legacyAta = $legacy->getAssociatedTokenAddressSync($mint, $owner);
        $extendedAta = $extended->getAssociatedTokenAddressSync($mint, $owner);

        self::assertNotSame($legacyAta->toBase58(), $extendedAta->toBase58());
    }
}
