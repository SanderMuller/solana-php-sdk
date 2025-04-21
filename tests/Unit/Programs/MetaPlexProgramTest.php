<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs;

use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MetaPlexProgramTest extends TestCase
{
    #[Test]
    public function gets_program_accounts(): void
    {
        $this->expectException(AccountNotFoundException::class);

        $this->container->get(SystemProgram::class)
            ->getAccountInfo('abc123');
    }
}
