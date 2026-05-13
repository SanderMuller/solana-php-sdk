<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Programs\SplTokenProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class SplProgramTest extends TestCase
{
    #[Test]
    public function get_token_accounts_by_owner(): void
    {
        self::markTestSkipped('Requires live RPC to list token accounts by owner.');
    }

    #[Test]
    public function get_associated_token_address_sync(): void
    {
        $mint = PublicKey::from('So11111111111111111111111111111111111111112');
        $owner = PublicKey::from('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y');
        $result = (new SplTokenProgram())->getAssociatedTokenAddressSync($mint, $owner, false);
        self::assertSame('8mFzQabNJVPstQHUFn7wqgvZyrxey3Qn7g2axD6roJCT', $result->toBase58());

        $owner2 = PublicKey::from('Atts2CLVXirnDsai6tCttdnAAyFwLqxqUd8zYbobgWCf');
        $result2 = (new SplTokenProgram())->getAssociatedTokenAddressSync($mint, $owner2, true);
        self::assertSame('AmDBTASE8BPvtAqgAPKeihZPgLqGMSWcStMbYbvZBmhk', $result2->toBase58());
    }

    #[Test]
    public function get_account(): void
    {
        self::markTestSkipped('Requires live RPC to fetch token account state.');
    }

    #[Test]
    public function get_or_create_associated_token_account(): void
    {
        self::markTestSkipped('Requires live RPC and funded payer to derive/create an ATA.');
    }

    #[Test]
    public function get_or_create_associated_token_account_does_not_exist(): void
    {
        self::markTestSkipped('Requires live RPC and funded payer to derive/create an ATA.');
    }

    #[Test]
    public function create_sync_native_instruction(): void
    {
        $syncNativeIx = (new SplTokenProgram())->createSyncNativeInstruction(
            owner: PublicKey::from('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y')
        );
        // SyncNative is just the discriminator byte (17). The previous test
        // expected 17 *zero bytes*, which is an invalid layout the runtime
        // would have rejected.
        self::assertSame([17], $syncNativeIx->data->toArray());
    }

    #[Test]
    public function create_associated_token_account_instruction(): void
    {
        $tokenProgramId = PublicKey::from(SplTokenProgram::TOKEN_PROGRAM_ID);
        $ataProgramId = PublicKey::from(SplTokenProgram::ASSOCIATED_TOKEN_PROGRAM_ID);

        $ix = (new SplTokenProgram())->createAssociatedTokenAccountInstruction(
            payer: PublicKey::from('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y'),
            associatedToken: PublicKey::from('DiRmKFukTVSAAGPmCFeH4ZEV6BtUcshZuACUF6Wp2ifL'),
            owner: PublicKey::from('ABCRVMBm2LBCVTxVuuxzwYiMqX8NTp6zzH9Tr6V2ZaJg'),
            mint: PublicKey::from('So11111111111111111111111111111111111111112'),
            programId: $tokenProgramId,
            associatedTokenProgramId: $ataProgramId,
        );

        self::assertTrue($ix->programId->equals($ataProgramId));
        self::assertTrue($ix->keys[5]->getPublicKey()->equals($tokenProgramId));
    }
}
