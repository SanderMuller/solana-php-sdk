<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

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

    #[Test]
    public function it_can_sign_and_verify_messages(): void
    {
        // 1. Generate a new keypair
        $keypair = Keypair::generate();

        // 2. Define a message
        $message = 'This is a test message for authentication.';

        // 3. Sign the message using the Keypair method
        $signature = $keypair->sign($message);

        // 4. Verify the signature using the Keypair method (should be true)
        $isValid = $keypair->verify($message, $signature);
        self::assertTrue($isValid, 'Signature should be valid with correct keypair and message.');

        // 5. Verify with wrong message (should be false)
        $isInvalidMessage = $keypair->verify('This is a wrong message.', $signature);
        self::assertFalse($isInvalidMessage, 'Signature should be invalid with incorrect message.');

        // 6. Verify with wrong keypair (should be false)
        $wrongKeypair = Keypair::generate();
        $isInvalidKey = $wrongKeypair->verify($message, $signature);
        self::assertFalse($isInvalidKey, 'Signature should be invalid with incorrect keypair.');

        // 7. Verify with invalid signature format (optional, but good practice)
        $isInvalidFormat = $keypair->verify($message, 'an_invalid_signature_string');
        self::assertFalse($isInvalidFormat, 'Signature should be invalid with incorrect format.');
    }
}
