<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use PHPUnit\Framework\Attributes\Test;

final class TransactionInstructionTest extends TestCase
{
    #[Test]
    public function icreates_transaction_instruction_with_program_id_and_keys(): void
    {
        $programId = PublicKey::fromString('3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX');
        $keys = [
            new AccountMeta($programId, true, true),
            new AccountMeta(PublicKey::fromString('3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk'), false, true),
        ];
        $data = 'some data';

        $instruction = new TransactionInstruction(programId: $programId, keys: $keys, data: $data);

        $this->assertEquals($programId, $instruction->programId);
        $this->assertEquals($keys, $instruction->keys);
        $this->assertSame($data, $instruction->data->toString());
    }

    #[Test]
    public function icreates_transaction_instruction_with_program_id_and_keys_without_data(): void
    {
        $programId = PublicKey::fromString('3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX');
        $keys = [
            new AccountMeta($programId, true, true),
            new AccountMeta(PublicKey::fromString('3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk'), false, true),
        ];

        $instruction = new TransactionInstruction($programId, $keys);

        $this->assertEquals($programId, $instruction->programId);
        $this->assertEquals($keys, $instruction->keys);
        $this->assertEquals(Buffer::empty(), $instruction->data);
    }
}
