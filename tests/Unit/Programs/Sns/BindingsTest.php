<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs\SNS;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Programs\SnsProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class BindingsTest extends TestCase
{
    #[Test]
    public function create_sub_domain_fast(): void
    {
        self::markTestSkipped('Requires live mainnet RPC for parent name registry lookup.');
    }

    #[Test]
    public function create_name_registry(): void
    {
        $nameOwnerSigner = PublicKey::generate();

        $connection = $this->container->get(Connection::class);
        $snsProgram = $this->container->get(SnsProgram::class);

        $instruction = $snsProgram->createNameRegistry(
            connection: $connection,
            name: 'domain',
            space: 2000,
            payerKey: $nameOwnerSigner,
            nameOwner: $nameOwnerSigner, // could be someone else
            lamports: 12345,
        );

        self::assertSame(0, $instruction->data->toArray()[0]);
    }
}
