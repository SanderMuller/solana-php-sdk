<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

final class TransactionInstructionTest extends TestCase
{
    #[Test]
    public function icreates_transaction_instruction_with_program_id_and_keys(): void
    {
        $programId = PublicKey::from('3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX');
        $keys = [
            new AccountMeta($programId, true, true),
            new AccountMeta(PublicKey::from('3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk'), false, true),
        ];
        $data = 'some data';

        $instruction = new TransactionInstruction(programId: $programId, keys: $keys, data: $data);

        self::assertEquals($programId, $instruction->programId);
        self::assertEquals($keys, $instruction->keys);
        self::assertSame($data, $instruction->data->toString());
    }

    #[Test]
    public function icreates_transaction_instruction_with_program_id_and_keys_without_data(): void
    {
        $programId = PublicKey::from('3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX');
        $keys = [
            new AccountMeta($programId, true, true),
            new AccountMeta(PublicKey::from('3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk'), false, true),
        ];

        $instruction = new TransactionInstruction($programId, $keys);

        self::assertEquals($programId, $instruction->programId);
        self::assertEquals($keys, $instruction->keys);
        self::assertEquals(Buffer::empty(), $instruction->data);
    }
}
