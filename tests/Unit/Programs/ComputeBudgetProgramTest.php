<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Programs\ComputeBudgetProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class ComputeBudgetProgramTest extends TestCase
{
    #[Test]
    public function set_compute_unit_limit_layout(): void
    {
        $ix = ComputeBudgetProgram::setComputeUnitLimit(300_000);
        $bytes = $ix->data->toArray();

        self::assertSame(ComputeBudgetProgram::PROGRAM_ID, $ix->programId->toBase58());
        // Discriminator = 2, payload = u32 LE.
        self::assertSame([2, 0xE0, 0x93, 0x04, 0x00], $bytes);
    }

    #[Test]
    public function set_compute_unit_price_layout(): void
    {
        $ix = ComputeBudgetProgram::setComputeUnitPrice(1_000_000);
        $bytes = $ix->data->toArray();

        // Discriminator = 3, payload = u64 LE = 1_000_000 -> 40 42 0F 00 00 00 00 00.
        self::assertSame([3, 0x40, 0x42, 0x0F, 0x00, 0x00, 0x00, 0x00, 0x00], $bytes);
    }

    #[Test]
    public function request_heap_frame_layout(): void
    {
        $ix = ComputeBudgetProgram::requestHeapFrame(32 * 1024);
        $bytes = $ix->data->toArray();

        self::assertSame([1, 0x00, 0x80, 0x00, 0x00], $bytes);
    }

    #[Test]
    public function set_loaded_accounts_data_size_limit_layout(): void
    {
        $ix = ComputeBudgetProgram::setLoadedAccountsDataSizeLimit(65_536);
        $bytes = $ix->data->toArray();

        self::assertSame([4, 0x00, 0x00, 0x01, 0x00], $bytes);
    }
}
