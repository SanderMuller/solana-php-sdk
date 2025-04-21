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
            NAME_PROGRAM_ID: PublicKey::generate(),
            programId: PublicKey::generate(),
            nameAccountKey: PublicKey::generate(),
            nameOwner: PublicKey::generate(),
            payerKey: PublicKey::generate(),
            hashed_name: Buffer::fromInt(32, BufferType::INT, false),
            param: Buffer::fromInt(1000000, BufferType::INT, false),
            param1: Buffer::fromInt(2000, BufferType::INT, false),
            nameClass: PublicKey::generate(),
            parentName: PublicKey::generate(),
            nameParentOwner: PublicKey::generate(),
        );

        self::assertInstanceOf(TransactionInstruction::class, $instruction);
        self::assertEquals(0, $instruction->data->toArray()[0]);

        // TODO: Add more assertions here to verify the properties of the returned TransactionInstruction
    }

    #[Test]
    public function update_instruction(): void
    {
        $instruction = $this->container->get(SnsProgram::class)->updateInstruction(
            nameProgramId: PublicKey::generate(),
            nameAccountKey: PublicKey::generate(),
            offset: Buffer::fromInt(96, BufferType::INT, false),
            input_data: Buffer::fromString('INPUT DATA'),
            nameUpdateSigner: PublicKey::generate(),
        );

        self::assertEquals(1, $instruction->data->toArray()[0]);
    }

    #[Test]
    public function transfer_instruction(): void
    {
        $instruction = $this->container->get(SnsProgram::class)->transferInstruction(
            NAME_PROGRAM_ID: PublicKey::generate(),
            pubkey: PublicKey::generate(),
            newOwner: PublicKey::generate(),
            owner: PublicKey::generate(),
            null: null,
            nameParent: null,
            nameParentOwner: null,
        );

        self::assertInstanceOf(TransactionInstruction::class, $instruction);
        self::assertEquals(2, $instruction->data->toArray()[0]);
    }

    #[Test]
    public function realloc_instruction(): void
    {
        $currentNameOwnerKey = PublicKey::generate();

        $instruction = $this->container->get(SnsProgram::class)->reallocInstruction(
            nameProgramId: PublicKey::generate(),
            systemProgramId: PublicKey::generate(),
            payerKey: $currentNameOwnerKey,
            nameAccountKey: PublicKey::generate(),
            nameOwnerKey: $currentNameOwnerKey,
            space: Buffer::fromInt(2000, BufferType::INT, false),
        );

        self::assertEquals(4, $instruction->data->toArray()[0]);
    }

    #[Test]
    public function delete_instruction(): void
    {
        $currentNameOwnerKey = PublicKey::generate();

        $instruction = $this->container->get(SnsProgram::class)->deleteInstruction(
            nameProgramId: PublicKey::generate(),
            nameAccountKey: PublicKey::generate(),
            refundTargetKey: $currentNameOwnerKey,
            nameOwnerKey: $currentNameOwnerKey
        );

        self::assertEquals(3, $instruction->data->toArray()[0]);
    }
}
