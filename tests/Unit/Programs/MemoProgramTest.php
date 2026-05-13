<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\MemoProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class MemoProgramTest extends TestCase
{
    #[Test]
    public function memo_writes_utf8_bytes_as_data(): void
    {
        $ix = MemoProgram::build('hello');

        self::assertSame(MemoProgram::PROGRAM_ID, $ix->programId->toBase58());
        self::assertSame([0x68, 0x65, 0x6C, 0x6C, 0x6F], $ix->data->toArray());
        self::assertSame([], $ix->keys);
    }

    #[Test]
    public function memo_with_signers_attaches_account_metas(): void
    {
        $alice = Keypair::generate()->getPublicKey();
        $bob = Keypair::generate()->getPublicKey();

        $ix = MemoProgram::build('signed memo', [$alice, $bob]);

        self::assertCount(2, $ix->keys);
        self::assertTrue($ix->keys[0]->isSigner);
        self::assertFalse($ix->keys[0]->isWritable);
        self::assertSame($alice->toBase58(), $ix->keys[0]->getPublicKey()->toBase58());
        self::assertSame($bob->toBase58(), $ix->keys[1]->getPublicKey()->toBase58());
    }

    #[Test]
    public function memo_supports_v1_program_id_override(): void
    {
        $ix = MemoProgram::build('legacy', [], MemoProgram::programIdV1());

        self::assertSame(MemoProgram::PROGRAM_ID_V1, $ix->programId->toBase58());
    }

    #[Test]
    public function memo_rejects_invalid_utf8(): void
    {
        $this->expectException(InputValidationException::class);

        MemoProgram::build("\xC3\x28"); // overlong / invalid utf-8 sequence
    }

    #[Test]
    public function empty_memo_is_allowed(): void
    {
        $ix = MemoProgram::build('');

        self::assertSame([], $ix->data->toArray());
    }
}
