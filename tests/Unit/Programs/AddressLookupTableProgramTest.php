<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\AddressLookupTableProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class AddressLookupTableProgramTest extends TestCase
{
    #[Test]
    public function create_emits_u32_discriminator_u64_slot_and_bump(): void
    {
        $authority = Keypair::generate()->getPublicKey();
        $payer = Keypair::generate()->getPublicKey();

        $result = AddressLookupTableProgram::createLookupTable($authority, $payer, 12345);

        $data = $result->instruction->data->toArray();

        // u32 LE discriminator 0
        self::assertSame([0, 0, 0, 0], array_slice($data, 0, 4));
        // u64 LE 12345 -> 0x39 0x30 ...
        self::assertSame([0x39, 0x30, 0, 0, 0, 0, 0, 0], array_slice($data, 4, 8));
        // u8 bump
        self::assertCount(13, $data);
        self::assertSame($result->bumpSeed & 0xFF, $data[12]);

        self::assertCount(4, $result->instruction->keys);
        self::assertTrue($result->instruction->keys[1]->isSigner);
        self::assertTrue($result->instruction->keys[2]->isSigner);
        self::assertTrue($result->instruction->keys[2]->isWritable);
    }

    #[Test]
    public function extend_serialises_vec_with_u64_length_prefix(): void
    {
        $authority = Keypair::generate()->getPublicKey();
        $payer = Keypair::generate()->getPublicKey();
        $lut = Keypair::generate()->getPublicKey();

        $entries = [
            Keypair::generate()->getPublicKey(),
            Keypair::generate()->getPublicKey(),
            Keypair::generate()->getPublicKey(),
        ];

        $ix = AddressLookupTableProgram::extendLookupTable($lut, $authority, $payer, $entries);
        $data = $ix->data->toArray();

        // u32 LE discriminator 2
        self::assertSame([2, 0, 0, 0], array_slice($data, 0, 4));
        // u64 LE 3
        self::assertSame([3, 0, 0, 0, 0, 0, 0, 0], array_slice($data, 4, 8));
        // Payload = 3 * 32 byte pubkeys
        self::assertCount(4 + 8 + 96, $data);
        self::assertSame($entries[0]->toBytes()[0], $data[12]);
        self::assertSame($entries[2]->toBytes()[31], $data[12 + 95]);
    }

    #[Test]
    public function freeze_deactivate_close_emit_bare_discriminator(): void
    {
        $authority = Keypair::generate()->getPublicKey();
        $lut = Keypair::generate()->getPublicKey();
        $recipient = Keypair::generate()->getPublicKey();

        self::assertSame([1, 0, 0, 0], AddressLookupTableProgram::freezeLookupTable($lut, $authority)->data->toArray());
        self::assertSame([3, 0, 0, 0], AddressLookupTableProgram::deactivateLookupTable($lut, $authority)->data->toArray());
        self::assertSame([4, 0, 0, 0], AddressLookupTableProgram::closeLookupTable($lut, $authority, $recipient)->data->toArray());

        self::assertCount(3, AddressLookupTableProgram::closeLookupTable($lut, $authority, $recipient)->keys);
    }

    #[Test]
    public function derive_lookup_table_address_is_deterministic(): void
    {
        $authority = Keypair::generate()->getPublicKey();

        [$pdaA] = AddressLookupTableProgram::deriveLookupTableAddress($authority, 100);
        [$pdaB] = AddressLookupTableProgram::deriveLookupTableAddress($authority, 100);

        self::assertSame($pdaA->toBase58(), $pdaB->toBase58());

        [$pdaC] = AddressLookupTableProgram::deriveLookupTableAddress($authority, 101);
        self::assertNotSame($pdaA->toBase58(), $pdaC->toBase58());
    }
}
