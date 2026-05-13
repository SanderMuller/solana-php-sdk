<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

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
