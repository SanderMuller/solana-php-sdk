<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PublicKeyTest extends TestCase
{
    #[Test]
    public function correctly_encodes_string_to_buffer(): void
    {
        $publicKey = PublicKey::fromString('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');

        $this->assertSame([
            23, 26, 218, 1, 26, 7, 253, 202, 19, 162, 251, 121, 172, 0, 65, 219, 142, 20, 252, 217, 6, 150, 142, 0, 54, 146, 245, 140, 155, 194, 42, 131,
        ], $publicKey->toArray());

        $this->assertSame('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc', $publicKey->toBase58());
    }

    #[Test]
    public function correctly_evaluates_equality(): void
    {
        $publicKey1 = PublicKey::fromString('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');
        $publicKey2 = PublicKey::fromString('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');

        $this->assertEquals($publicKey1, $publicKey2);
    }

    #[Test]
    public function correctly_handles_public_key_in_constructor(): void
    {
        $publicKey1 = PublicKey::fromString('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');
        $publicKey2 = PublicKey::fromBuffer($publicKey1);

        $this->assertEquals($publicKey1, $publicKey2);
        $this->assertTrue($publicKey1->equals($publicKey2));
    }

    #[Test]
    public function equals(): void
    {
        $arrayKey = PublicKey::fromArray([3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);

        $base58Key = PublicKey::fromString('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3');

        $this->assertEquals($base58Key, $arrayKey);
        $this->assertTrue($base58Key->equals($arrayKey));
    }

    #[Test]
    public function toBase58(): void
    {
        $key1 = PublicKey::fromString('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3');
        $this->assertSame('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3', $key1->toBase58());
        $this->assertEquals('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3', $key1);

        $key2 = PublicKey::fromString('1111111111111111111111111111BukQL');
        $this->assertSame('1111111111111111111111111111BukQL', $key2->toBase58());
        $this->assertEquals('1111111111111111111111111111BukQL', $key2);

        $key3 = PublicKey::fromString('11111111111111111111111111111111');
        $this->assertSame('11111111111111111111111111111111', $key3->toBase58());

        $key4 = PublicKey::fromArray([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
        $this->assertSame('11111111111111111111111111111111', $key4->toBase58());
    }

    #[Test]
    public function createWithSeed(): void
    {
        $defaultPublicKey = PublicKey::fromString('11111111111111111111111111111111');
        $derivedKey = PublicKey::createWithSeed($defaultPublicKey, 'limber chicken: 4/45', $defaultPublicKey);

        $this->assertEquals(PublicKey::fromString('9h1HyLCW5dZnBVap8C5egQ9Z6pHyjsh5MNy83iPqqRuq'), $derivedKey);
    }

    #[Test]
    public function createProgramAddress(): void
    {
        $programId = PublicKey::fromString('BPFLoader1111111111111111111111111111111111');
        $publicKey = PublicKey::fromString('SeedPubey1111111111111111111111111111111111');

        $programAddress = PublicKey::createProgramAddress([
            '',
            [1],
        ], $programId);
        $this->assertEquals(PublicKey::fromString('3gF2KMe9KiC6FNVBmfg9i267aMPvK37FewCip4eGBFcT'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            '☉',
        ], $programId);
        $this->assertEquals(PublicKey::fromString('7ytmC1nT1xY4RfxCV2ZgyA7UakC93do5ZdyhdF3EtPj7'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            'Talking',
            'Squirrels',
        ], $programId);
        $this->assertEquals(PublicKey::fromString('HwRVBufQ4haG5XSgpspwKtNd3PC9GM9m1196uJW36vds'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            $publicKey->toBytes(),
        ], $programId);
        $this->assertEquals(PublicKey::fromString('GUs5qLUfsEHkcMB9T38vjr18ypEhRuNWiePW2LoK4E3K'), $programAddress);
    }

    #[Test]
    public function findProgramAddress(): void
    {
        $programId = PublicKey::fromString('BPFLoader1111111111111111111111111111111111');

        [$programAddress, $nonce] = PublicKey::findProgramAddress(
            [''],
            $programId
        );

        $this->assertEquals(
            PublicKey::createProgramAddress([
                '',
                [$nonce],
            ], $programId),
            $programAddress
        );
    }

    #[Test]
    public function isOnCurve(): void
    {
        $onCurvePublicKey = Keypair::generate()->getPublicKey();
        $this->assertTrue(PublicKey::isOnCurve($onCurvePublicKey));

        // A program address, yanked from one of the above tests. This is a pretty
        // poor test vector since it was created by the same code it is testing.
        // Unfortunately, I've been unable to find a golden negative example input
        // for curve25519 point decompression :/
        $offCurve = PublicKey::fromString('12rqwuEgBYiGhBrDJStCiqEtzQpTTiZbh7teNVLuYcFA');
        $this->assertFalse(PublicKey::isOnCurve($offCurve));

        /**
         * @see https://github.com/verze-app/solana-php-sdk/issues/31
         */
        $onCurve = PublicKey::fromString('q5xfHVuAsGmKRTmJNshERHHkVnHKxRX3cKzz5cYnFRn');
        $this->assertTrue(PublicKey::isOnCurve($onCurve));
    }
}
