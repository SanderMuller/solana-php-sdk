<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Queue;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Events\TransactionConfirmed;
use SanderMuller\SolanaPhpSdk\Events\TransactionExpired;
use SanderMuller\SolanaPhpSdk\Events\TransactionExpiredReason;
use SanderMuller\SolanaPhpSdk\Queue\ConfirmTransactionJob;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

/** @internal */
final class ConfirmTransactionJobTest extends TestCase
{
    #[Test]
    public function fires_confirmed_event_when_status_lands(): void
    {
        Event::fake();

        $this->fakeRpcByMethod([
            'getSignatureStatuses' => [
                'context' => ['slot' => 1],
                'value' => [[
                    'slot' => 100,
                    'confirmations' => null,
                    'confirmationStatus' => 'confirmed',
                    'err' => null,
                ]],
            ],
        ]);

        $job = new ConfirmTransactionJob(
            signature: 'sig-1',
            pollIntervalMs: 1,
            context: ['order_id' => 99],
        );

        $job->handle($this->container->make(Connection::class));

        Event::assertDispatched(
            TransactionConfirmed::class,
            static fn (TransactionConfirmed $e): bool => $e->signature === 'sig-1'
                && $e->context['order_id'] === 99,
        );
    }

    #[Test]
    public function fires_expired_event_on_failed_on_chain(): void
    {
        Event::fake();

        $this->fakeRpcByMethod([
            'getSignatureStatuses' => [
                'context' => ['slot' => 1],
                'value' => [[
                    'slot' => 100,
                    'confirmations' => null,
                    'confirmationStatus' => 'confirmed',
                    'err' => ['InstructionError' => [0, ['Custom' => 1]]],
                ]],
            ],
        ]);

        $job = new ConfirmTransactionJob(signature: 'sig-fail', pollIntervalMs: 1);
        $job->handle($this->container->make(Connection::class));

        Event::assertDispatched(
            TransactionExpired::class,
            static fn (TransactionExpired $e): bool => $e->reason === TransactionExpiredReason::FailedOnChain,
        );
    }
}
