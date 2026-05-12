<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Feature;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Transaction;
use Collectiq\SolanaPhpSdk\Util\Commitment;
use PHPUnit\Framework\Attributes\Test;

final class ConnectionFeatureTest extends TestCase
{
    #[Test]
    public function get_account_info_feature(): void
    {
        $connection = $this->container->get(Connection::class);

        // Act: Call the getAccountInfo method with a real public key
        $pubKey = 'Atts2CLVXirnDsai6tCttdnAAyFwLqxqUd8zYbobgWCf';
        $result = $connection->getAccountInfo($pubKey);

        // Assert: Check the result is as expected
        // This will depend on what the actual response from the Solana API looks like
        self::assertEquals('11111111111111111111111111111111', $result['owner']);
    }

    #[Test]
    public function get_transaction(): void
    {
        $connection = $this->container->get(Connection::class);

        $txn = '3ScP26YbYarMTQBA6i3a9NynrXj845FNX3afmgTWiZAAhqVrZwyw5YbMhuqczamjBLwWZ3XNY91nrRCeVNMjtexE';
        $result = $connection->getTransaction($txn);

        self::assertSame($txn, $result?->signature);
    }

    #[Test]
    public function get_latest_blockhash(): void
    {
        $connection = $this->container->get(Connection::class);

        $result = $connection->getLatestBlockhash(Commitment::finalized());

        self::assertNotNull($result['blockhash']);
    }

    #[Test]
    public function ge_recent_blockhash(): void
    {
        $result = $this->container->get(Connection::class)
            ->getLatestBlockhash(new Commitment('finalized'));

        self::assertNotNull($result['blockhash']);
    }

    #[Test]
    public function get_confirmed_transaction(): void
    {
        $txn = '3ScP26YbYarMTQBA6i3a9NynrXj845FNX3afmgTWiZAAhqVrZwyw5YbMhuqczamjBLwWZ3XNY91nrRCeVNMjtexE';

        $result = $this->container->get(Connection::class)->getTransaction($txn);

        self::assertSame($txn, $result?->signature);
    }

    #[Test]
    public function simulate_transaction(): void
    {
        $connection = $this->container->get(Connection::class);
        $recentBlockhash = $connection->getLatestBlockhash(Commitment::finalized())['blockhash'];

        $account1 = Keypair::generate();
        $account2 = Keypair::generate();
        $transfer1 = SystemProgram::transfer($account1->getPublicKey(), $account2->getPublicKey(), 123);
        $transfer2 = SystemProgram::transfer($account2->getPublicKey(), $account1->getPublicKey(), 123);

        $orgTransaction = new Transaction($recentBlockhash);

        $orgTransaction->addInstructions($transfer1, $transfer2);
        $orgTransaction->sign($account1, $account2);

        $newTransaction = new Transaction(
            recentBlockhash: $orgTransaction->recentBlockhash,
            nonceInformation: null,
            feePayer: null,
            signatures: $orgTransaction->signatures,
        );
        $newTransaction->addInstructions($transfer1, $transfer2);

        $response = $connection->simulateTransaction(
            transaction: $newTransaction,
            signers: [$account1, $account2],
        );

        self::assertEquals('AccountNotFound', $response['value']['err']);
    }

    #[Test]
    public function send_transaction(): void
    {
        $connection = $this->container->get(Connection::class);
        // ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y -- must have sol ( airdrop sol )
        $secretKey = json_decode('[45,54,39,107,89,97,142,99,78,79,179,20,100,88,176,123,63,144,15,102,152,62,187,243,16,83,234,7,115,196,73,58,136,86,43,13,28,152,130,148,70,247,159,0,0,197,176,80,47,230,51,124,29,148,39,41,36,61,88,254,63,143,109,69]');

        $account1 = Keypair::fromSecretKey($secretKey);
        $account2 = PublicKey::from('BURNKKWBSaXmUFQPaABzWWtQ97U2oByNtPiXz3cCAMpq');

        $transfer1 = SystemProgram::transfer($account1->getPublicKey(), $account2->getPublicKey(), 123);

        $orgTransaction = new Transaction();

        $orgTransaction->addInstructions($transfer1);

        $response = $connection->sendTransaction($orgTransaction, [$account1], ['skipPreflight' => false]);
        self::assertIsString($response);

        $tx2 = new Transaction();
        $tx2->addInstructions($transfer1);

        $response = $connection->sendTransaction($tx2, [$account1]);
        self::assertIsString($response);
    }

    #[Test]
    public function get_balance(): void
    {
        $connection = $this->container->get(Connection::class);

        self::assertIsFloat($connection->getBalance('ABCexcAcjLuEsZUbaudqATgUp4MUL5STNAjr3goRLk6Y'));
    }

    #[Test]
    public function get_minimum_balance_for_rent_exemption(): void
    {
        $connection = $this->container->get(Connection::class);

        self::assertIsInt($connection->getMinimumBalanceForRentExemption(2000));
    }
}
