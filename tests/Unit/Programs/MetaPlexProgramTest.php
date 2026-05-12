<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MetaPlexProgramTest extends TestCase
{
    #[Test]
    public function gets_program_accounts(): void
    {
        $this->expectException(AccountNotFoundException::class);

        $this->container->get(Connection::class)
            ->getAccountInfo('abc123');
    }
}
