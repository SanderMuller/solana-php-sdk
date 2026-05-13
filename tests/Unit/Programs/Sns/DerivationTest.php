<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs\SNS;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Programs\SnsProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class DerivationTest extends TestCase
{
    /**
     * @var array<mixed, array<string, string>>
     */
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
    public function get_hashed_name_sync(): void
    {
        $hashedName = new SnsProgram()->getHashedNameSync('bonfida');
        $bs58HashedName = $hashedName->toBase58String();
        self::assertSame('AcmVjPtaDyNboWGSKjYHxea1QDgN648T4Je3HUpkHecf', $bs58HashedName);
    }

    #[Test]
    public function derive_synch(): void
    {
        $sns = new SnsProgram();
        $hashedName = $sns->_deriveSync('bonfida');
        $nameAccountKey = $sns->getNameAccountKeySync($hashedName['hashed']);
        $nameAccountKeyBs58 = $nameAccountKey->toBase58();
        self::assertSame('85v6oF1VnGNeT4oV2fH8HpVBj3k3U4m6uNnWYT8AcA5H', $nameAccountKeyBs58);
    }

    #[Test]
    public function get_domain_key_sync(): void
    {
        $sns = new SnsProgram();
        foreach ($this->items as $item) {
            $result = $sns->getDomainKeySync($item['domain']);
            self::assertSame($item['address'], $result['pubkey']->toBase58());
        }
    }

    #[Test]
    public function get_reverse_key_sync(): void
    {
        $sns = new SnsProgram();
        foreach ($this->items as $item) {
            $result = $sns->getReverseKeySync($item['domain']);
            self::assertTrue($result->toBase58() === 'DqgmWxe2PPrfy45Ja3UPyFGwcbRzkRuwXt3NyxjX8krg' ||
            $result->toBase58() === 'BrRErziYEA9oBoDyYrdVF9p6Gs1QtdpaZ6AQpaybeZgf');
        }
    }

    #[Test]
    public function get_name_owner(): void
    {
        self::markTestSkipped('Requires live mainnet RPC to resolve name registry owner.');
    }
}
