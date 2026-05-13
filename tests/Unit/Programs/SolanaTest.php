<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class SolanaTest extends TestCase
{
    #[Test]
    public function will_throw_exception_when_rpc_account_response_is_null(): void
    {
        $this->expectException(AccountNotFoundException::class);
        $this->container->get(Connection::class)->getAccountInfo('abc123');
    }

    #[Test]
    public function config(): void
    {
        self::assertSame('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA', (new SystemProgram())->config('TOKEN_PROGRAM_ID'));
    }
}
