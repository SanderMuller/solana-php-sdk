<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use PHPUnit\Framework\Attributes\Test;

final class KeypairTest extends TestCase
{
    /**
     * Seeded from
     * https://github.com/solana-labs/solana-web3.js/blob/master/test/keypair.test.ts
     * on Oct 2, 2021
     */
    #[Test]
    public function inew_keypair(): void
    {
        $keypair = Keypair::generate();

        self::assertCount(64, $keypair->getSecretKey());
        self::assertCount(32, $keypair->getPublicKey()->toBytes());
    }

    #[Test]
    public function igenerate_new_keypair(): void
    {
        $keypair = Keypair::generate();

        self::assertCount(64, $keypair->getSecretKey());
        self::assertCount(32, $keypair->getPublicKey()->toBytes());
    }

    #[Test]
    public function ikeypair_from_secret_key(): void
    {
        $secretKey = sodium_base642bin('mdqVWeFekT7pqy5T49+tV12jO0m+ESW7ki4zSU9JiCgbL0kJbj5dvQ/PqcDAzZLZqzshVEs01d1KZdmLh4uZIg==', SODIUM_BASE64_VARIANT_ORIGINAL);

        $keypair = Keypair::fromSecretKey($secretKey);

        self::assertSame('2q7pyhPwAwZ3QMfZrnAbDhnh9mDUqycszcpf86VgQxhF', $keypair->getPublicKey()->toBase58());
    }

    #[Test]
    public function igenerate_keypair_from_seed(): void
    {
        $byteArray = array_fill(0, 32, 8);

        $seedString = pack('C*', ...$byteArray);

        $keypair = Keypair::fromSeed($seedString);

        self::assertSame('2KW2XRd9kwqet15Aha2oK3tYvd3nWbTFH1MBiRAv1BE1', $keypair->getPublicKey()->toBase58());
    }

    #[Test]
    public function ibin2array_and_array2bin_are_equivalent(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);

        $valueAsArray = Buffer::from($publicKey)->toArray();
        $valueAsString = Buffer::from($valueAsArray)->toString();

        self::assertSame($publicKey, $valueAsString);
    }
}
