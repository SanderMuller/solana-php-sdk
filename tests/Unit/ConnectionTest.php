<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\Services\SolanaRpcClient;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Transaction;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class ConnectionTest extends TestCase
{
    #[Test]
    public function simulate_transaction(): void
    {
        $account1 = Keypair::generate();
        $account2 = Keypair::generate();
        $recentBlockhash = $account1->getPublicKey()->toBase58(); // Fake recentBlockhash

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

        // TODO - Fix this test, call the method and compare the transactions

        self::assertEquals($orgTransaction, $newTransaction);
    }

    #[Test]
    public function get_balance(): void
    {
        $pubKey = '3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX';
        $balance = 100;

        $clientMock = Mockery::mock(SolanaRpcClient::class);
        $clientMock->expects('call')
            ->with('getBalance', [$pubKey])
            ->andReturn(['value' => $balance]);

        $this->container->bind(SolanaRpcClient::class, static fn (): SolanaRpcClient => $clientMock);

        $connection = $this->container->get(Connection::class);

        $result = $connection->getBalance($pubKey);

        self::assertSame((float) $balance, $result);
    }
}
