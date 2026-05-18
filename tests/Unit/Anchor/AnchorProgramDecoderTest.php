<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Anchor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\SolanaPhpSdk\Anchor\AnchorIdl;
use SanderMuller\SolanaPhpSdk\Anchor\AnchorProgramDecoder;
use SanderMuller\SolanaPhpSdk\Borsh\BinaryWriter;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\DecodedAccountRef;

/** @internal */
final class AnchorProgramDecoderTest extends TestCase
{
    #[Test]
    public function decodes_an_instruction_with_a_u64_arg(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_minimal.json');
        $decoder = new AnchorProgramDecoder($idl);

        // Build the instruction bytes the same way IdlInstruction::build() would.
        $writer = new BinaryWriter();
        foreach ([11, 18, 104, 9, 104, 174, 59, 33] as $byte) {
            $writer->writeU8($byte);
        }
        $writer->writeU64(1_234_567);
        $bytes = self::bytesToString($writer->toArray());

        $decoded = $decoder->decode($bytes, []);

        self::assertNotNull($decoded);
        self::assertSame('increment', $decoded['name']);
        self::assertSame(['amount' => 1_234_567], $decoded['args']);
        self::assertArrayHasKey('accountNames', $decoded);
        self::assertSame(['counter', 'user'], $decoded['accountNames']);
    }

    #[Test]
    public function decodes_complex_args_string_vec_array_option_pubkey_bool(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_minimal.json');
        $decoder = new AnchorProgramDecoder($idl);

        $owner = PublicKey::from('11111111111111111111111111111111');

        $writer = new BinaryWriter();
        foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $byte) {
            $writer->writeU8($byte);
        }
        // label: string
        $writer->writeString('alpha');
        // tags: vec<string>  → u32 length + items
        $writer->writeU32(2);
        $writer->writeString('one');
        $writer->writeString('two');
        // checksum: array<u8; 4>
        $writer->writeU8(10);
        $writer->writeU8(20);
        $writer->writeU8(30);
        $writer->writeU8(40);
        // owner: pubkey
        $writer->writeFixedArray($owner->toBytes());
        // maxValue: option<u32> → some(42)
        $writer->writeU8(1);
        $writer->writeU32(42);
        // active: bool
        $writer->writeU8(1);

        $decoded = $decoder->decode(self::bytesToString($writer->toArray()), []);

        self::assertNotNull($decoded);
        self::assertSame('setLabel', $decoded['name']);
        self::assertSame('alpha', $decoded['args']['label']);
        self::assertSame(['one', 'two'], $decoded['args']['tags']);
        self::assertSame([10, 20, 30, 40], $decoded['args']['checksum']);
        self::assertSame($owner->toBase58(), $decoded['args']['owner']);
        self::assertSame(42, $decoded['args']['maxValue']);
        self::assertTrue($decoded['args']['active']);
    }

    #[Test]
    public function returns_null_when_discriminator_does_not_match(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_minimal.json');
        $decoder = new AnchorProgramDecoder($idl);

        $unknownDiscriminator = str_repeat("\xff", 8);

        self::assertNull($decoder->decode($unknownDiscriminator, []));
    }

    #[Test]
    public function returns_null_when_payload_is_shorter_than_discriminator(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_minimal.json');
        $decoder = new AnchorProgramDecoder($idl);

        self::assertNull($decoder->decode("\x01\x02", []));
    }

    #[Test]
    public function decodes_user_defined_struct(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_user_types.json');
        $decoder = new AnchorProgramDecoder($idl);

        $writer = new BinaryWriter();
        foreach ([10, 20, 30, 40, 50, 60, 70, 80] as $byte) {
            $writer->writeU8($byte);
        }
        // profile: { age: u8=42, tag: string="hi" }
        $writer->writeU8(42);
        $writer->writeString('hi');
        // status: enum tag 0 (Pending, unit)
        $writer->writeU8(0);

        $decoded = $decoder->decode(self::bytesToString($writer->toArray()), []);

        self::assertNotNull($decoded);
        self::assertSame(['age' => 42, 'tag' => 'hi'], $decoded['args']['profile']);
        self::assertSame(['Pending' => true], $decoded['args']['status']);
    }

    #[Test]
    public function decodes_enum_with_struct_payload(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_user_types.json');
        $decoder = new AnchorProgramDecoder($idl);

        $writer = new BinaryWriter();
        foreach ([10, 20, 30, 40, 50, 60, 70, 80] as $byte) {
            $writer->writeU8($byte);
        }
        // profile
        $writer->writeU8(1);
        $writer->writeString('');
        // status: tag 1 (Active { since: u32 = 1700 })
        $writer->writeU8(1);
        $writer->writeU32(1_700);

        $decoded = $decoder->decode(self::bytesToString($writer->toArray()), []);

        self::assertNotNull($decoded);
        self::assertSame(['Active' => ['since' => 1_700]], $decoded['args']['status']);
    }

    #[Test]
    public function decodes_enum_with_tuple_payload(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_user_types.json');
        $decoder = new AnchorProgramDecoder($idl);

        $writer = new BinaryWriter();
        foreach ([10, 20, 30, 40, 50, 60, 70, 80] as $byte) {
            $writer->writeU8($byte);
        }
        $writer->writeU8(1);
        $writer->writeString('');
        // status: tag 2 (Rejected(string))
        $writer->writeU8(2);
        $writer->writeString('nope');

        $decoded = $decoder->decode(self::bytesToString($writer->toArray()), []);

        self::assertNotNull($decoded);
        self::assertSame(['Rejected' => ['nope']], $decoded['args']['status']);
    }

    #[Test]
    public function attaches_idl_account_names_to_accounts(): void
    {
        $idl = AnchorIdl::fromFile(dirname(__DIR__, 2) . '/Fixtures/anchor_idl_minimal.json');
        $decoder = new AnchorProgramDecoder($idl);

        $writer = new BinaryWriter();
        foreach ([11, 18, 104, 9, 104, 174, 59, 33] as $byte) {
            $writer->writeU8($byte);
        }
        $writer->writeU64(1);

        $accounts = [
            new DecodedAccountRef(0, 'CounterPDA', false, true),
            new DecodedAccountRef(1, 'UserPubKey', true, false),
        ];

        $decoded = $decoder->decode(self::bytesToString($writer->toArray()), $accounts);

        self::assertNotNull($decoded);
        self::assertArrayHasKey('accountNames', $decoded);
        self::assertSame(['counter', 'user'], $decoded['accountNames']);
        unset($accounts); // exercised through the decode result, not mutated by the decoder
    }

    /**
     * @param array<int, int> $bytes
     */
    private static function bytesToString(array $bytes): string
    {
        $s = '';
        foreach ($bytes as $b) {
            $s .= chr($b & 0xFF);
        }

        return $s;
    }
}
