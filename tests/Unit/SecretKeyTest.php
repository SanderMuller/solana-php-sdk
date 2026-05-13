<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class SecretKeyTest extends TestCase
{
    #[Test]
    public function get_public_key_derives_matching_pubkey(): void
    {
        $keypair = Keypair::generate();
        $secretKey = $keypair->getSecretKey();

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
