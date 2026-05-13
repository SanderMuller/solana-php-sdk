<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Anchor;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Anchor\AnchorIdl;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

/**
 * Drives `AnchorIdl` against `tests/Fixtures/anchor_idl_minimal.json`, a
 * three-instruction Anchor 0.30 IDL covering the most-used arg shapes
 * (`u64`, `string`, `vec`, `array`, `pubkey`, `option`, `bool`).
 *
 * The fixture's discriminators are the real Anchor-shipped values for
 * `initialize` (`af af 6d 1f 0d 98 9b ed`) and a few placeholder bytes for
 * the other instructions — the encoder doesn't care, it just prepends them.
 */
final class AnchorIdlTest extends TestCase
{
    private AnchorIdl $idl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idl = AnchorIdl::fromFile(__DIR__ . '/../../Fixtures/anchor_idl_minimal.json');
    }

    #[Test]
    public function metadata_is_parsed_from_idl(): void
    {
        self::assertSame('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA', $this->idl->programId);
        self::assertSame('counter', $this->idl->name);
        self::assertSame('0.1.0', $this->idl->version);
        self::assertSame(['initialize', 'increment', 'setLabel'], $this->idl->instructionNames());
    }

    #[Test]
    public function build_initialize_with_no_args(): void
    {
        $counter = Keypair::generate()->getPublicKey();
        $user = Keypair::generate()->getPublicKey();

        $ix = $this->idl->instruction('initialize')->build(
            accounts: ['counter' => $counter, 'user' => $user],
        );

        self::assertSame('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA', $ix->programId->toBase58());
        self::assertCount(3, $ix->keys);
        self::assertTrue($ix->keys[0]->getPublicKey()->equals($counter));
        self::assertTrue($ix->keys[0]->isWritable);
        self::assertFalse($ix->keys[0]->isSigner);
        self::assertTrue($ix->keys[1]->isSigner);
        // systemProgram pulled from the IDL's fixed `address` field.
        self::assertSame('11111111111111111111111111111111', $ix->keys[2]->getPublicKey()->toBase58());

        // Just the 8-byte discriminator, no further args.
        self::assertSame([175, 175, 109, 31, 13, 152, 155, 237], $ix->data->toArray());
    }

    #[Test]
    public function build_increment_with_u64_arg(): void
    {
        $counter = Keypair::generate()->getPublicKey();
        $user = Keypair::generate()->getPublicKey();

        $ix = $this->idl->instruction('increment')->build(
            accounts: ['counter' => $counter, 'user' => $user],
            args: ['amount' => 0x0102030405060708],
        );

        $bytes = $ix->data->toArray();

        // Discriminator (8 bytes) + u64 little-endian payload.
        self::assertSame([11, 18, 104, 9, 104, 174, 59, 33], array_slice($bytes, 0, 8));
        self::assertSame([0x08, 0x07, 0x06, 0x05, 0x04, 0x03, 0x02, 0x01], array_slice($bytes, 8, 8));
    }

    #[Test]
    public function build_set_label_with_complex_args(): void
    {
        $counter = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $ix = $this->idl->instruction('setLabel')->build(
            accounts: ['counter' => $counter],
            args: [
                'label' => 'hi',
                'tags' => ['a', 'bb'],
                'checksum' => [1, 2, 3, 4],
                'owner' => $owner,
                'maxValue' => null,
                'active' => true,
            ],
        );

        $bytes = $ix->data->toArray();

        // Discriminator
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_slice($bytes, 0, 8));

        // string 'hi' = u32 length(2) LE + 'h','i'
        self::assertSame([2, 0, 0, 0, 0x68, 0x69], array_slice($bytes, 8, 6));

        // vec<string> = u32 count(2) + (u32 len(1)+'a') + (u32 len(2)+'bb')
        $offset = 14;
        self::assertSame([2, 0, 0, 0], array_slice($bytes, $offset, 4));
        self::assertSame([1, 0, 0, 0, 0x61], array_slice($bytes, $offset + 4, 5));
        self::assertSame([2, 0, 0, 0, 0x62, 0x62], array_slice($bytes, $offset + 9, 6));

        // array<u8,4> — no length prefix
        $offset += 15;
        self::assertSame([1, 2, 3, 4], array_slice($bytes, $offset, 4));

        // pubkey — 32 bytes verbatim
        $offset += 4;
        self::assertSame($owner->toBytes(), array_slice($bytes, $offset, 32));

        // option<u32> = 0 tag (None)
        $offset += 32;
        self::assertSame(0, $bytes[$offset]);

        // bool true = 1
        self::assertSame(1, $bytes[$offset + 1]);

        // No trailing bytes.
        self::assertCount($offset + 2, $bytes);
    }

    #[Test]
    public function option_some_value_writes_payload(): void
    {
        $counter = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $ix = $this->idl->instruction('setLabel')->build(
            accounts: ['counter' => $counter],
            args: [
                'label' => '',
                'tags' => [],
                'checksum' => [0, 0, 0, 0],
                'owner' => $owner,
                'maxValue' => 0xDEADBEEF,
                'active' => false,
            ],
        );

        $bytes = $ix->data->toArray();

        // …skip 8 discriminator + 4 (empty string len) + 4 (empty vec count)
        // + 4 (zero checksum) + 32 (pubkey) → option tag is at offset 52.
        self::assertSame(1, $bytes[52]); // Some
        self::assertSame([0xEF, 0xBE, 0xAD, 0xDE], array_slice($bytes, 53, 4));
        self::assertSame(0, $bytes[57]); // bool false
    }

    #[Test]
    public function missing_required_account_raises(): void
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessageMatches('/requires account `user`/');

        $counter = Keypair::generate()->getPublicKey();
        $this->idl->instruction('initialize')->build(accounts: ['counter' => $counter]);
    }

    #[Test]
    public function missing_required_arg_raises(): void
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessageMatches('/missing required arg `amount`/');

        $counter = Keypair::generate()->getPublicKey();
        $user = Keypair::generate()->getPublicKey();
        $this->idl->instruction('increment')->build(
            accounts: ['counter' => $counter, 'user' => $user],
            args: [],
        );
    }

    #[Test]
    public function discriminator_falls_back_to_sha256_when_omitted(): void
    {
        $idl = AnchorIdl::fromArray([
            'address' => '11111111111111111111111111111111',
            'metadata' => ['name' => 'legacy'],
            'instructions' => [
                ['name' => 'initialize', 'accounts' => [], 'args' => []],
            ],
        ]);

        // sha256("global:initialize")[0:8] = af af 6d 1f 0d 98 9b ed
        self::assertSame(
            [175, 175, 109, 31, 13, 152, 155, 237],
            $idl->instruction('initialize')->discriminator,
        );
    }

    #[Test]
    public function unknown_instruction_raises(): void
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessageMatches('/Instruction unknown not defined/');

        $this->idl->instruction('unknown');
    }
}
