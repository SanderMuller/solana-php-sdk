<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs\SNS;

use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\Programs\SnsProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use PHPUnit\Framework\Attributes\Test;

final class InstructionsTest extends TestCase
{
    #[Test]
    public function create_instruction(): void
    {
        $instruction = $this->container->get(SnsProgram::class)->createInstruction(
            NAME_PROGRAM_ID: PublicKey::fromBuffer(Buffer::alloc(32)),
            programId: PublicKey::fromBuffer(Buffer::alloc(32)),
            nameAccountKey: PublicKey::fromBuffer(Buffer::alloc(32)),
            nameOwner: PublicKey::fromBuffer(Buffer::alloc(32)),
            payerKey: PublicKey::fromBuffer(Buffer::alloc(32)),
            hashed_name: Buffer::fromInt(32, BufferType::INT, false),
            param: Buffer::fromInt(1000000, BufferType::INT, false),
            param1: Buffer::fromInt(2000, BufferType::INT, false),
            nameClass: PublicKey::fromBuffer(Buffer::alloc(32)),
            parentName: PublicKey::fromBuffer(Buffer::alloc(32)),
            nameParentOwner: PublicKey::fromBuffer(Buffer::alloc(32)),
        );

        $this->assertInstanceOf(TransactionInstruction::class, $instruction);
        $this->assertEquals(0, $instruction->data->toArray()[0]);

        // TODO: Add more assertions here to verify the properties of the returned TransactionInstruction
    }

    #[Test]
    public function updateInstruction(): void
    {
        $instruction = $this->container->get(SnsProgram::class)->updateInstruction(
            nameProgramId: PublicKey::fromBuffer(Buffer::alloc(32)),
            nameAccountKey: PublicKey::fromBuffer(Buffer::alloc(32)),
            offset: Buffer::fromInt(96, BufferType::INT, false),
            input_data: Buffer::fromString('INPUT DATA'),
            nameUpdateSigner: PublicKey::fromBuffer(Buffer::alloc(32)),
        );

        $this->assertEquals(1, $instruction->data->toArray()[0]);
    }

    #[Test]
    public function transferInstruction(): void
    {
        $instruction = $this->container->get(SnsProgram::class)->transferInstruction(
            NAME_PROGRAM_ID: PublicKey::fromBuffer(Buffer::alloc(32)),
            pubkey: PublicKey::fromBuffer(Buffer::alloc(32)),
            newOwner: PublicKey::fromBuffer(Buffer::alloc(32)),
            owner: PublicKey::fromBuffer(Buffer::alloc(32)),
            null: null,
            nameParent: null,
            nameParentOwner: null,
        );

        $this->assertInstanceOf(TransactionInstruction::class, $instruction);
        $this->assertEquals(2, $instruction->data->toArray()[0]);
    }

    #[Test]
    public function reallocInstruction(): void
    {
        $currentNameOwnerKey = PublicKey::fromBuffer(Buffer::alloc(32));

        $instruction = $this->container->get(SnsProgram::class)->reallocInstruction(
            nameProgramId: PublicKey::fromBuffer(Buffer::alloc(32)),
            systemProgramId: PublicKey::fromBuffer(Buffer::alloc(32)),
            payerKey: $currentNameOwnerKey,
            nameAccountKey: PublicKey::fromBuffer(Buffer::alloc(32)),
            nameOwnerKey: $currentNameOwnerKey,
            space: Buffer::fromInt(2000, BufferType::INT, false),
        );

        $this->assertEquals(4, $instruction->data->toArray()[0]);
    }

    #[Test]
    public function deleteInstruction(): void
    {
        $currentNameOwnerKey = PublicKey::fromBuffer(Buffer::alloc(32));

        $instruction = $this->container->get(SnsProgram::class)->deleteInstruction(
            nameProgramId: PublicKey::fromBuffer(Buffer::alloc(32)),
            nameAccountKey: PublicKey::fromBuffer(Buffer::alloc(32)),
            refundTargetKey: $currentNameOwnerKey,
            nameOwnerKey: $currentNameOwnerKey
        );

        $this->assertEquals(3, $instruction->data->toArray()[0]);
    }
}
