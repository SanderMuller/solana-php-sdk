<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use stdClass;

final class ProgramAccountsPagedTest extends TestCase
{
    #[Test]
    public function paged_walk_emits_one_request_per_data_size_bucket(): void
    {
        // Two distinct buckets, one row each. The generator should yield
        // both rows across the two underlying RPC calls.
        $calls = 0;
        $this->fakeRpcByMethod([
            'getProgramAccounts' => function () use (&$calls): array {
                $calls++;
                $payload = base64_encode('hi');

                return [[
                    'pubkey' => '11111111111111111111111111111111',
                    'account' => [
                        'lamports' => $calls,
                        'owner' => '11111111111111111111111111111111',
                        'executable' => false,
                        'rentEpoch' => 0,
                        'data' => [$payload, 'base64'],
                    ],
                ]];
            },
        ]);

        $connection = $this->container->get(Connection::class);

        $rows = [];
        foreach ($connection->programAccountsPaged(PublicKey::default(), [82, 165]) as $row) {
            $rows[] = $row;
        }

        self::assertCount(2, $rows);
        self::assertSame(2, $calls);
        // Each bucket call produced an account with its call-index as lamports.
        self::assertSame(1, $rows[0]->account->lamports);
        self::assertSame(2, $rows[1]->account->lamports);
    }

    #[Test]
    public function paged_walk_is_lazy(): void
    {
        // Generator must not start scanning until iteration begins.
        // Counter held on a stdClass so PHPStan doesn't fold the
        // by-reference int back to its literal 0 across the closure call.
        $calls = new stdClass();
        $calls->n = 0;
        $this->fakeRpcByMethod([
            'getProgramAccounts' => function () use ($calls): array {
                $calls->n++;

                return [];
            },
        ]);

        $connection = $this->container->get(Connection::class);
        $gen = $connection->programAccountsPaged(PublicKey::default(), [82]);

        self::assertSame(0, $calls->n);
        iterator_to_array($gen);
        self::assertSame(1, $calls->n);
    }
}
