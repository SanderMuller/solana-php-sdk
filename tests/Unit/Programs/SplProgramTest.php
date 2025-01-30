<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Programs\SplToken\State\Account;
use Collectiq\SolanaPhpSdk\Programs\SplTokenProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SplProgramTest extends TestCase
{
    #[Test]
    public function get_token_accounts_by_owner(): void
    {
        $result = new SplTokenProgram()->getTokenAccountsByOwner('Atts2CLVXirnDsai6tCttdnAAyFwLqxqUd8zYbobgWCf');

        self::assertNotNull($result['value'][0]['pubkey']);
    }

    #[Test]
    public function get_associated_token_address_sync(): void
    {
        $mint = PublicKey::from('So11111111111111111111111111111111111111112');
        $owner = PublicKey::from('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y');
        $result = new SplTokenProgram()->getAssociatedTokenAddressSync($mint, $owner, false);
        self::assertSame('8mFzQabNJVPstQHUFn7wqgvZyrxey3Qn7g2axD6roJCT', $result->toBase58());

        $owner2 = PublicKey::from('Atts2CLVXirnDsai6tCttdnAAyFwLqxqUd8zYbobgWCf');
        $result2 = new SplTokenProgram()->getAssociatedTokenAddressSync($mint, $owner2, true);
        self::assertSame('AmDBTASE8BPvtAqgAPKeihZPgLqGMSWcStMbYbvZBmhk', $result2->toBase58());
    }

    #[Test]
    public function get_account(): void
    {
        $publicKey = PublicKey::from('AmDBTASE8BPvtAqgAPKeihZPgLqGMSWcStMbYbvZBmhk');

        $account = Account::getAccount(
            connection: $this->container->get(Connection::class),
            accountPublicKeyOnbject: $publicKey,
        );

        self::assertTrue($account->owner->equals($publicKey));
     }

    #[Test]
    public function get_or_create_associated_token_account(): void
    {
        // ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y -- must have sol ( airdrop sol )
        $secretKey = json_decode('[45,54,39,107,89,97,142,99,78,79,179,20,100,88,176,123,63,144,15,102,152,62,187,243,16,83,234,7,115,196,73,58,136,86,43,13,28,152,130,148,70,247,159,0,0,197,176,80,47,230,51,124,29,148,39,41,36,61,88,254,63,143,109,69]');
        $payerSigner = Keypair::fromSecretKey($secretKey);

        self::assertSame('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y', $payerSigner->getPublicKey()->toBase58());

        $account = new SplTokenProgram()->getOrCreateAssociatedTokenAccount(
            connection: $this->container->get(Connection::class),
            payer: $payerSigner,
            mint: PublicKey::from('So11111111111111111111111111111111111111112'),
            owner: PublicKey::from('ABCRVMBm2LBCVTxVuuxzwYiMqX8NTp6zzH9Tr6V2ZaJg'),
            allowOwnerOffCurve: true,
        );

        $accountAddress = $account->address->toBase58();
        self::assertSame('DiRmKFukTVSAAGPmCFeH4ZEV6BtUcshZuACUF6Wp2ifL', $accountAddress);
    }

    #[Test]
    public function get_or_create_associated_token_account_does_not_exist(): void
    {
        // ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y -- must have sol ( airdrop sol )
        $secretKey = json_decode('[45,54,39,107,89,97,142,99,78,79,179,20,100,88,176,123,63,144,15,102,152,62,187,243,16,83,234,7,115,196,73,58,136,86,43,13,28,152,130,148,70,247,159,0,0,197,176,80,47,230,51,124,29,148,39,41,36,61,88,254,63,143,109,69]');
        $payerSigner = Keypair::fromSecretKey($secretKey);

        self::assertSame('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y', $payerSigner->getPublicKey()->toBase58());

        try {
            new SplTokenProgram()->getOrCreateAssociatedTokenAccount(
                connection: $this->container->get(Connection::class),
                payer: $payerSigner,
                mint: PublicKey::from('So11111111111111111111111111111111111111112'),
                owner: Keypair::generate()->getPublicKey(),
                allowOwnerOffCurve: true,
            );
        } catch (AccountNotFoundException|GenericException) {
            self::assertTrue(true);

            return;
        }

        self::fail('Expected AccountNotFoundException or GenericException not thrown');
    }

    #[Test]
    public function create_sync_native_instruction(): void
    {
        $syncNativeIx = new SplTokenProgram()->createSyncNativeInstruction(
            owner: PublicKey::from('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y')
        );
        self::assertNotNull($syncNativeIx);
        self::assertCount(17, $syncNativeIx->data);
    }

    #[Test]
    public function create_associated_token_account_instruction(): void
    {
        $programId = PublicKey::from(SplTokenProgram::TOKEN_PROGRAM_ID);

        $ix = new SplTokenProgram()->createAssociatedTokenAccountInstruction(
            payer: PublicKey::from('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y'),
            associatedToken: PublicKey::from('DiRmKFukTVSAAGPmCFeH4ZEV6BtUcshZuACUF6Wp2ifL'),
            owner: PublicKey::from('ABCRVMBm2LBCVTxVuuxzwYiMqX8NTp6zzH9Tr6V2ZaJg'),
            mint: PublicKey::from('So11111111111111111111111111111111111111112'),
            programId: $programId,
            associatedTokenProgramId: PublicKey::from(SplTokenProgram::ASSOCIATED_TOKEN_PROGRAM_ID),
        );

        self::assertTrue($ix->programId->equals($programId));
    }
}
