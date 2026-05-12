<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Util\Commitment;
use PHPUnit\Framework\Attributes\Test;

final class ConfirmTransactionTest extends TestCase
{
    #[Test]
    public function returns_status_once_commitment_reached(): void
    {
        $calls = 0;
        $this->fakeRpcByMethod([
            'getSignatureStatuses' => function () use (&$calls): array {
                $calls++;

                return [
                    'context' => ['slot' => 100],
                    'value' => [
                        $calls < 2
                            ? null
                            : ['slot' => 99, 'confirmations' => 5, 'confirmationStatus' => 'confirmed', 'err' => null],
                    ],
                ];
            },
        ]);

        $status = $this->container->get(Connection::class)
            ->confirmTransaction('sig123', Commitment::confirmed(), timeoutSeconds: 5, pollIntervalMs: 10);

        self::assertSame('confirmed', $status->confirmationStatus);
        self::assertGreaterThanOrEqual(2, $calls);
    }

    #[Test]
    public function raises_when_chain_returns_err(): void
    {
        $this->fakeRpcByMethod([
            'getSignatureStatuses' => [
                'context' => ['slot' => 100],
                'value' => [[
                    'slot' => 50,
                    'confirmations' => null,
                    'confirmationStatus' => 'finalized',
                    'err' => ['InstructionError' => [0, 'Custom']],
                ]],
            ],
        ]);

        $this->expectException(GenericException::class);
        $this->expectExceptionMessageMatches('/failed on-chain/');

        $this->container->get(Connection::class)
            ->confirmTransaction('sigBad', Commitment::confirmed(), timeoutSeconds: 5, pollIntervalMs: 10);
    }

    #[Test]
    public function raises_when_blockhash_expires(): void
    {
        $this->fakeRpcByMethod([
            'getSignatureStatuses' => [
                'context' => ['slot' => 100],
                'value' => [null],
            ],
            'getBlockHeight' => 9_999,
        ]);

        $this->expectException(GenericException::class);
        $this->expectExceptionMessageMatches('/Blockhash expired/');

        $this->container->get(Connection::class)
            ->confirmTransaction('sigStale', Commitment::confirmed(), lastValidBlockHeight: 100, timeoutSeconds: 5, pollIntervalMs: 10);
    }

    #[Test]
    public function blockhash_expiry_uses_processed_tip_even_when_target_is_finalized(): void
    {
        // Slow target commitment (`finalized`) on a chain whose finalized tip
        // is still behind the expired blockhash height. The check must compare
        // against the `processed` tip to detect expiration; otherwise the
        // method would block until the timeout.
        $calls = [];

        $this->fakeRpcByMethod([
            'getSignatureStatuses' => [
                'context' => ['slot' => 1000],
                'value' => [null],
            ],
            'getBlockHeight' => static function (array $body) use (&$calls): int {
                $commitment = $body['params'][0]['commitment'] ?? 'default';
                $calls[] = $commitment;

                // Live tip ('processed') already past expiry; finalized still behind.
                return $commitment === 'finalized' ? 80 : 250;
            },
        ]);

        $this->expectException(GenericException::class);
        $this->expectExceptionMessageMatches('/Blockhash expired/');

        try {
            $this->container->get(Connection::class)
                ->confirmTransaction('sigStaleFin', Commitment::finalized(), lastValidBlockHeight: 200, timeoutSeconds: 5, pollIntervalMs: 10);
        } finally {
            self::assertContains('processed', $calls, 'Expiry probe should poll with processed commitment.');
            self::assertNotContains('finalized', $calls, 'Expiry probe should NOT poll with finalized commitment.');
        }
    }
}
