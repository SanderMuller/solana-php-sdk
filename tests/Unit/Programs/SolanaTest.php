<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs;

use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SolanaTest extends TestCase
{
    #[Test]
    public function will_throw_exception_when_rpc_account_response_is_null(): void
    {
        $this->expectException(AccountNotFoundException::class);
        new SystemProgram()->getAccountInfo('abc123');
    }

    #[Test]
    public function config(): void
    {
        self::assertEquals('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA', new SystemProgram()->config('TOKEN_PROGRAM_ID'));
    }
}
