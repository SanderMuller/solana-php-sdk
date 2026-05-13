<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class PublicKeyTest extends TestCase
{
    #[Test]
    public function correctly_encodes_string_to_buffer(): void
    {
        $publicKey = PublicKey::from('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');

        self::assertSame([
            23, 26, 218, 1, 26, 7, 253, 202, 19, 162, 251, 121, 172, 0, 65, 219, 142, 20, 252, 217, 6, 150, 142, 0, 54, 146, 245, 140, 155, 194, 42, 131,
        ], $publicKey->toBytes());

        self::assertSame('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc', $publicKey->toBase58());
    }

    #[Test]
    public function correctly_evaluates_equality(): void
    {
        $publicKey1 = PublicKey::from('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');
        $publicKey2 = PublicKey::from('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');

        self::assertEquals($publicKey1, $publicKey2);
    }

    #[Test]
    public function correctly_handles_public_key_in_constructor(): void
    {
        $publicKey1 = PublicKey::from('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');
        $publicKey2 = PublicKey::from($publicKey1);

        self::assertEquals($publicKey1, $publicKey2);
        self::assertTrue($publicKey1->equals($publicKey2));
    }

    #[Test]
    public function equals(): void
    {
        $arrayKey = PublicKey::from([3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);

        $base58Key = PublicKey::from('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3');

        self::assertEquals($base58Key, $arrayKey);
        self::assertTrue($base58Key->equals($arrayKey));
    }

    #[Test]
    public function to_base58(): void
    {
        $key1 = PublicKey::from('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3');
        self::assertSame('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3', $key1->toBase58());
        self::assertSame('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3', (string) $key1);

        $key2 = PublicKey::from('1111111111111111111111111111BukQL');
        self::assertSame('1111111111111111111111111111BukQL', $key2->toBase58());
        self::assertSame('1111111111111111111111111111BukQL', (string) $key2);

        $key3 = PublicKey::from('11111111111111111111111111111111');
        self::assertSame('11111111111111111111111111111111', $key3->toBase58());

        $key4 = PublicKey::from([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
        self::assertSame('11111111111111111111111111111111', $key4->toBase58());
    }

    #[Test]
    public function create_with_seed(): void
    {
        $defaultPublicKey = PublicKey::from('11111111111111111111111111111111');
        $derivedKey = PublicKey::createWithSeed($defaultPublicKey, 'limber chicken: 4/45', $defaultPublicKey);

        self::assertEquals(PublicKey::from('9h1HyLCW5dZnBVap8C5egQ9Z6pHyjsh5MNy83iPqqRuq'), $derivedKey);
    }

    #[Test]
    public function create_program_address(): void
    {
        $programId = PublicKey::from('BPFLoader1111111111111111111111111111111111');
        $publicKey = PublicKey::from('SeedPubey1111111111111111111111111111111111');

        $programAddress = PublicKey::createProgramAddress([
            '',
            [1],
        ], $programId);
        self::assertEquals(PublicKey::from('3gF2KMe9KiC6FNVBmfg9i267aMPvK37FewCip4eGBFcT'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            '☉',
        ], $programId);
        self::assertEquals(PublicKey::from('7ytmC1nT1xY4RfxCV2ZgyA7UakC93do5ZdyhdF3EtPj7'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            'Talking',
            'Squirrels',
        ], $programId);
        self::assertEquals(PublicKey::from('HwRVBufQ4haG5XSgpspwKtNd3PC9GM9m1196uJW36vds'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            $publicKey->toBytes(),
        ], $programId);
        self::assertEquals(PublicKey::from('GUs5qLUfsEHkcMB9T38vjr18ypEhRuNWiePW2LoK4E3K'), $programAddress);
    }

    #[Test]
    public function find_program_address(): void
    {
        $programId = PublicKey::from('BPFLoader1111111111111111111111111111111111');

        [$programAddress, $nonce] = PublicKey::findProgramAddress(
            [''],
            $programId
        );

        self::assertEquals(PublicKey::createProgramAddress([
            '',
            [$nonce],
        ], $programId), $programAddress);
    }

    #[Test]
    public function is_on_curve(): void
    {
        self::assertTrue(PublicKey::isOnCurve(Keypair::generate()->getPublicKey()));

        // A program address, yanked from one of the above tests. This is a pretty
        // poor test vector since it was created by the same code it is testing.
        // Unfortunately, I've been unable to find a golden negative example input
        // for curve25519 point decompression :/
        $offCurve = PublicKey::from('12rqwuEgBYiGhBrDJStCiqEtzQpTTiZbh7teNVLuYcFA');
        self::assertFalse(PublicKey::isOnCurve($offCurve));

        /**
         * @see https://github.com/verze-app/solana-php-sdk/issues/31
         */
        $onCurve = PublicKey::from('q5xfHVuAsGmKRTmJNshERHHkVnHKxRX3cKzz5cYnFRn');
        self::assertTrue(PublicKey::isOnCurve($onCurve));
    }

    #[Test]
    public function verify_returns_true_for_valid_signature(): void
    {
        $keypair = Keypair::generate();
        $message = 'sign-in nonce: 0e9c3f2a-1d4b-4e7a-9f8c-2b5d6e8a1c3f';
        $signature = $keypair->sign($message);

        self::assertTrue($keypair->getPublicKey()->verify($message, $signature));
    }

    #[Test]
    public function verify_returns_false_for_wrong_message(): void
    {
        $keypair = Keypair::generate();
        $signature = $keypair->sign('original');

        self::assertFalse($keypair->getPublicKey()->verify('tampered', $signature));
    }

    #[Test]
    public function verify_returns_false_for_wrong_pubkey(): void
    {
        $signer = Keypair::generate();
        $other = Keypair::generate();
        $message = 'msg';
        $signature = $signer->sign($message);

        self::assertFalse($other->getPublicKey()->verify($message, $signature));
    }

    #[Test]
    public function verify_throws_on_signature_length_mismatch(): void
    {
        $publicKey = Keypair::generate()->getPublicKey();

        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('Invalid signature length. Expected 64. Found: 5');

        $publicKey->verify('msg', 'short');
    }
}
