<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\SecretKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SecretKeyTest extends TestCase
{
    #[Test]
    public function get_public_key_derives_matching_pubkey(): void
    {
        $keypair = Keypair::generate();
        $secretKey = $keypair->getSecretKey();

        self::assertInstanceOf(SecretKey::class, $secretKey);
        self::assertTrue(
            $secretKey->getPublicKey()->equals($keypair->getPublicKey()),
            'SecretKey::getPublicKey() must derive the same pubkey as the parent Keypair.',
        );
    }

    #[Test]
    public function get_secret_key_returns_self(): void
    {
        $secretKey = Keypair::generate()->getSecretKey();

        self::assertSame($secretKey, $secretKey->getSecretKey());
    }
}
