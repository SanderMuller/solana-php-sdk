<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs\SNS;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Enum\Buffer\BufferType;
use SanderMuller\SolanaPhpSdk\Programs\SnsProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

final class InstructionsTest extends TestCase
{
    #[Test]
    public function create_instruction(): void
    {
        $instruction = $this->container->get(SnsProgram::class)->createInstruction(
            nameProgramId: PublicKey::generate(),
            systemProgramId: PublicKey::generate(),
            nameKey: PublicKey::generate(),
            nameOwnerKey: PublicKey::generate(),
            payerKey: PublicKey::generate(),
            hashed_name: Buffer::fromInt(32, BufferType::INT, false),
            lamports: Buffer::fromInt(1000000, BufferType::INT, false),
            space: Buffer::fromInt(2000, BufferType::INT, false),
            nameClassKey: PublicKey::generate(),
            nameParent: PublicKey::generate(),
            nameParentOwner: PublicKey::generate(),
        );

        self::assertInstanceOf(TransactionInstruction::class, $instruction);
        self::assertSame(0, $instruction->data->toArray()[0]);

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

        self::assertSame(1, $instruction->data->toArray()[0]);
    }

    #[Test]
    public function transfer_instruction(): void
    {
        $instruction = $this->container->get(SnsProgram::class)->transferInstruction(
            nameProgramId: PublicKey::generate(),
            nameAccountKey: PublicKey::generate(),
            newOwnerKey: PublicKey::generate(),
            currentNameOwnerKey: PublicKey::generate(),
        );

        self::assertInstanceOf(TransactionInstruction::class, $instruction);
        self::assertSame(2, $instruction->data->toArray()[0]);
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

        self::assertSame(4, $instruction->data->toArray()[0]);
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

        self::assertSame(3, $instruction->data->toArray()[0]);
    }
}
