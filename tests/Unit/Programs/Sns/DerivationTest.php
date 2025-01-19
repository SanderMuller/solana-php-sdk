<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs\SNS;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Programs\SnsProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\SolanaRpcClient;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DerivationTest extends TestCase
{
    private array $items = [
        [
            'domain' => 'bonfida',
            'address' => 'Crf8hzfthWGbGbLTVCiqRqV5MVnbpHB1L9KQMd6gsinb',
        ],
        [
            'domain' => 'bonfida.sol',
            'address' => 'Crf8hzfthWGbGbLTVCiqRqV5MVnbpHB1L9KQMd6gsinb',
        ],
        [
            'domain' => 'dex.bonfida',
            'address' => 'HoFfFXqFHAC8RP3duuQNzag1ieUwJRBv1HtRNiWFq4Qu',
        ],
        [
            'domain' => 'dex.bonfida.sol',
            'address' => 'HoFfFXqFHAC8RP3duuQNzag1ieUwJRBv1HtRNiWFq4Qu',
        ],
    ];

    #[Test]
    public function getHashedNameSync(): void
    {
        $client = $this->createMock(SolanaRpcClient::class);
        $sns = new SnsProgram($client);
        $hashedName = $sns->getHashedNameSync('bonfida');
        $bs58HashedName = $hashedName->toBase58String();
        $this->assertSame('AcmVjPtaDyNboWGSKjYHxea1QDgN648T4Je3HUpkHecf', $bs58HashedName);
    }

    #[Test]
    public function deriveSynch(): void
    {
        $client = $this->createMock(SolanaRpcClient::class);
        $sns = new SnsProgram($client);
        $hashedName = $sns->_deriveSync('bonfida');
        $nameAccountKey = $sns->getNameAccountKeySync($hashedName['hashed']);
        $nameAccountKeyBs58 = $nameAccountKey->toBase58();
        $this->assertSame('85v6oF1VnGNeT4oV2fH8HpVBj3k3U4m6uNnWYT8AcA5H', $nameAccountKeyBs58);
    }

    #[Test]
    public function getDomainKeySync(): void
    {
        $client = $this->createMock(SolanaRpcClient::class);
        $sns = new SnsProgram($client);
        foreach ($this->items as $item) {
            $result = $sns->getDomainKeySync($item['domain']);
            $this->assertInstanceOf(PublicKey::class, $result['pubkey']);
            $this->assertEquals($item['address'], $result['pubkey']->toBase58());
        }
    }

    #[Test]
    public function getReverseKeySync(): void
    {
        $client = $this->createMock(SolanaRpcClient::class);
        $sns = new SnsProgram($client);
        foreach ($this->items as $item) {
            $result = $sns->getReverseKeySync($item['domain']);
            $this->assertInstanceOf(PublicKey::class, $result);
            $this->assertTrue(
                $result->toBase58() === 'DqgmWxe2PPrfy45Ja3UPyFGwcbRzkRuwXt3NyxjX8krg' ||
                $result->toBase58() === 'BrRErziYEA9oBoDyYrdVF9p6Gs1QtdpaZ6AQpaybeZgf'
            );
        }
    }

    #[Test]
    public function getNameOwner(): void
    {
        config(['solana-php-sdk.network' => Network::MAINNET]);

        $connection = $this->container->get(Connection::class);
        $sns = new SnsProgram();
        $nameAccountKey = 'HoFfFXqFHAC8RP3duuQNzag1ieUwJRBv1HtRNiWFq4Qu';
        $result = $sns->getNameOwner($connection, $nameAccountKey);
        $owner = $result['registry']->owner;
        $parent = $result['registry']->parentName;
        $this->assertInstanceOf(PublicKey::class, $owner);
        $this->assertSame('CnNHzcp7L4jKiA2Rsca3hZyVwSmoqXaT8wGwzS8WvvB2', $owner->toBase58());
        $this->assertEquals('Crf8hzfthWGbGbLTVCiqRqV5MVnbpHB1L9KQMd6gsinb', $parent->toBase58());
    }
}
