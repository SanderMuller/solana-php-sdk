<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Programs\SplToken\State\Account;
use Collectiq\SolanaPhpSdk\Programs\SplTokenProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\SolanaRpcClient;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SplProgramTest extends TestCase
{
    #[Test]
    public function get_token_accounts_by_owner(): void
    {
        $result = new SplTokenProgram()->getTokenAccountsByOwner('Atts2CLVXirnDsai6tCttdnAAyFwLqxqUd8zYbobgWCf');
        $this->assertNotNull($result['value'][0]['pubkey']);
    }

    #[Test]
    public function get_associated_token_address_sync(): void
    {
        $splProgram = new SplTokenProgram();
        $mint = PublicKey::fromString('So11111111111111111111111111111111111111112');
        $owner = PublicKey::fromString('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y');
        $result = $splProgram->getAssociatedTokenAddressSync($mint, $owner, false);
        $this->assertSame('8mFzQabNJVPstQHUFn7wqgvZyrxey3Qn7g2axD6roJCT', $result->toBase58());
        $splProgram = new SplTokenProgram();
        $owner2 = PublicKey::fromString('Atts2CLVXirnDsai6tCttdnAAyFwLqxqUd8zYbobgWCf');
        $result2 = $splProgram->getAssociatedTokenAddressSync($mint, $owner2, true);
        $this->assertSame('AmDBTASE8BPvtAqgAPKeihZPgLqGMSWcStMbYbvZBmhk', $result2->toBase58());
    }

    #[Test]
    public function get_account(): void
    {
        $connection = $this->container->get(SolanaRpcClient::class);

        $account = Account::getAccount($connection, PublicKey::fromString('AmDBTASE8BPvtAqgAPKeihZPgLqGMSWcStMbYbvZBmhk'));
        $this->assertNotNull($account);
     }

    #[Test]
    public function get_or_create_associated_token_account(): void
    {
        $connection = $this->container->get(Connection::class);
        $splProgram = new SplTokenProgram();
        // ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y -- must have sol ( airdrop sol )
        $secretKey = json_decode('[45,54,39,107,89,97,142,99,78,79,179,20,100,88,176,123,63,144,15,102,152,62,187,243,16,83,234,7,115,196,73,58,136,86,43,13,28,152,130,148,70,247,159,0,0,197,176,80,47,230,51,124,29,148,39,41,36,61,88,254,63,143,109,69]');
        $payerSigner = Keypair::fromSecretKey($secretKey);
        $signer = $payerSigner->getPublicKey()->toBase58();

        $this->assertSame('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y', $signer);
        // A new account with SOL (airdrop Sol)
        $owner = PublicKey::fromString('ABCRVMBm2LBCVTxVuuxzwYiMqX8NTp6zzH9Tr6V2ZaJg');
        // ATA DiRmKFukTVSAAGPmCFeH4ZEV6BtUcshZuACUF6Wp2ifL
        $mint = PublicKey::fromString('So11111111111111111111111111111111111111112');
        $account = $splProgram->getOrCreateAssociatedTokenAccount(
            $connection,
            $payerSigner,
            $mint,
            $owner,
            true);
        $accountAddress = $account->address->toBase58();
        $this->assertSame('DiRmKFukTVSAAGPmCFeH4ZEV6BtUcshZuACUF6Wp2ifL', $accountAddress);
    }

    #[Test]
    public function get_or_create_associated_token_account_does_not_exist(): void
    {
        $connection = $this->container->get(Connection::class);
        $splProgram = new SplTokenProgram();
        // ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y -- must have sol ( airdrop sol )
        $secretKey = json_decode('[45,54,39,107,89,97,142,99,78,79,179,20,100,88,176,123,63,144,15,102,152,62,187,243,16,83,234,7,115,196,73,58,136,86,43,13,28,152,130,148,70,247,159,0,0,197,176,80,47,230,51,124,29,148,39,41,36,61,88,254,63,143,109,69]');
        $payerSigner = Keypair::fromSecretKey($secretKey);
        $signer = $payerSigner->getPublicKey()->toBase58();

        $this->assertSame('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y', $signer);
        // A new account with SOL (airdrop Sol)

        $owner = new Keypair();
        // ATA Random
        $mint = PublicKey::fromString('So11111111111111111111111111111111111111112');
        try {
            $splProgram->getOrCreateAssociatedTokenAccount(
                $connection,
                $payerSigner,
                $mint,
                $owner->getPublicKey(),
                true);
        } catch (AccountNotFoundException|GenericException) {
            $this->assertTrue(true);

            return;
        }

        $this->fail('Expected AccountNotFoundException or GenericException not thrown');

    }

    #[Test]
    public function create_sync_native_instruction(): void
    {
        $splProgram = new SplTokenProgram();
        $owner = PublicKey::fromString('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y');
        $syncNativeIx = $splProgram->createSyncNativeInstruction($owner);
        $this->assertNotNull($syncNativeIx);
        $this->assertCount(17, $syncNativeIx->data);
    }

    #[Test]
    public function create_associated_token_account_instruction(): void
    {
        $splProgram = new SplTokenProgram();
        $payer = PublicKey::fromString('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y');
        $associatedToken = PublicKey::fromString('DiRmKFukTVSAAGPmCFeH4ZEV6BtUcshZuACUF6Wp2ifL');
        $owner = PublicKey::fromString('ABCRVMBm2LBCVTxVuuxzwYiMqX8NTp6zzH9Tr6V2ZaJg');
        $mint = PublicKey::fromString('So11111111111111111111111111111111111111112');
        $programId = PublicKey::fromString(SplTokenProgram::TOKEN_PROGRAM_ID);
        $associatedTokenProgramId = PublicKey::fromString(SplTokenProgram::ASSOCIATED_TOKEN_PROGRAM_ID);

        $ix = $splProgram->createAssociatedTokenAccountInstruction(
            $payer,
            $associatedToken,
            $owner,
            $mint,
            $programId,
            $associatedTokenProgramId
        );
        $this->assertNotNull($ix);
    }
}
