<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs\SNS;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Programs\SnsProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\SolanaRpcClient;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use PHPUnit\Framework\Attributes\Test;

final class BindingsTest extends TestCase
{
    #[Test]
    public function create_sub_domain_fast(): void
    {
        $nameOwnerKey = PublicKey::from('6V3DAZhWgATw8hrmMh7DnvLgaVpHLuMafZZPTVnyUs6Y');

        config(['solana-php-sdk.network' => Network::MAINNET]);

        $connection = $this->container->get(Connection::class);
        $sns = new SnsProgram();

        $instruction = $sns->createSubdomainFast(
            connection: $connection,
            subdomain: 'subdomain.chongkan.sol',
            subdomainPk: PublicKey::from('57vj6H1omWUvrQypM8esx4q67WNRZhTW3ZHZ97unkSTb'), // f.chongkan.sol
            parentPk: PublicKey::from('34MxBdMJYgugd9ZzmZN338kL1vMqkhPqtnZG5qmWnfn1'),
            owner: $nameOwnerKey,
            space: 1_000,
            feePayer: $nameOwnerKey,
        );

        self::assertInstanceOf(TransactionInstruction::class, $instruction[1][0]);

        // TODO Assert IX keys and data
    }

    #[Test]
    public function createNameRegistry(): void
    {
        $nameOwnerSigner = PublicKey::generate();

        $connection = $this->container->get(SolanaRpcClient::class);
        $snsProgram = $this->container->get(SnsProgram::class);

        $instruction = $snsProgram->createNameRegistry(
            connection: $connection,
            name: 'domain',
            space: 2000,
            payerKey: $nameOwnerSigner,
            nameOwner: $nameOwnerSigner, // could be someone else
        );

        self::assertEquals(0, $instruction->data->toArray()[0]);
    }
}
