<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\BlockhashExpiredException;
use SanderMuller\SolanaPhpSdk\Exceptions\ConfirmationTimeoutException;
use SanderMuller\SolanaPhpSdk\Exceptions\GenericException;
use SanderMuller\SolanaPhpSdk\Exceptions\TransactionFailedOnChainException;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Util\Commitment;

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

        $this->expectException(TransactionFailedOnChainException::class);
        $this->expectExceptionMessageMatches('/failed on-chain/');

        $this->container->get(Connection::class)
            ->confirmTransaction('sigBad', Commitment::confirmed(), timeoutSeconds: 5, pollIntervalMs: 10);
    }

    #[Test]
    public function on_chain_failure_exception_extends_generic_for_back_compat(): void
    {
        // Hosts still catching the legacy `GenericException` must keep
        // working after the typed exception was introduced. Verified at
        // the class-string level so PHPStan does not fold the assertion.
        $parents = class_parents(TransactionFailedOnChainException::class);
        self::assertIsArray($parents);
        self::assertContains(GenericException::class, $parents);

        $this->fakeRpcByMethod([
            'getSignatureStatuses' => [
                'context' => ['slot' => 1],
                'value' => [[
                    'slot' => 1, 'confirmations' => null, 'confirmationStatus' => 'finalized',
                    'err' => ['InstructionError' => [0, 'Custom']],
                ]],
            ],
        ]);

        try {
            $this->container->get(Connection::class)
                ->confirmTransaction('sigBC', Commitment::confirmed(), timeoutSeconds: 5, pollIntervalMs: 10);
            self::fail('expected exception');
        } catch (TransactionFailedOnChainException $transactionFailedOnChainException) {
            self::assertSame('sigBC', $transactionFailedOnChainException->signature);
            self::assertSame(['InstructionError' => [0, 'Custom']], $transactionFailedOnChainException->err);
        }
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

        $this->expectException(BlockhashExpiredException::class);
        $this->expectExceptionMessageMatches('/Blockhash expired/');

        $this->container->get(Connection::class)
            ->confirmTransaction('sigStale', Commitment::confirmed(), lastValidBlockHeight: 100, timeoutSeconds: 5, pollIntervalMs: 10);
    }

    #[Test]
    public function raises_timeout_with_typed_exception(): void
    {
        $this->fakeRpcByMethod([
            'getSignatureStatuses' => [
                'context' => ['slot' => 1],
                'value' => [null],
            ],
        ]);

        try {
            $this->container->get(Connection::class)
                ->confirmTransaction('sigSlow', Commitment::confirmed(), timeoutSeconds: 1, pollIntervalMs: 10);
            self::fail('expected exception');
        } catch (ConfirmationTimeoutException $confirmationTimeoutException) {
            self::assertSame('sigSlow', $confirmationTimeoutException->signature);
            self::assertSame(1, $confirmationTimeoutException->timeoutSeconds);
        }
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
                $params = $body['params'] ?? [];
                $first = is_array($params) ? ($params[0] ?? []) : [];
                $commitment = is_array($first) ? ($first['commitment'] ?? 'default') : 'default';
                $commitmentStr = is_string($commitment) ? $commitment : 'default';
                $calls[] = $commitmentStr;

                // Live tip ('processed') already past expiry; finalized still behind.
                return $commitmentStr === 'finalized' ? 80 : 250;
            },
        ]);

        $this->expectException(BlockhashExpiredException::class);
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
