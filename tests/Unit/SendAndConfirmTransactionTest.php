<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\GenericException;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Transaction;
use SanderMuller\SolanaPhpSdk\Util\Commitment;

final class SendAndConfirmTransactionTest extends TestCase
{
    #[Test]
    public function send_and_confirm_round_trips_through_send_and_status(): void
    {
        $sendCalls = 0;
        $statusCalls = 0;

        $this->fakeRpcByMethod([
            'getLatestBlockhash' => [
                'context' => ['slot' => 1],
                'value' => ['blockhash' => '11111111111111111111111111111111', 'lastValidBlockHeight' => 200],
            ],
            'sendTransaction' => function () use (&$sendCalls): string {
                $sendCalls++;

                return 'sigOK';
            },
            'getSignatureStatuses' => function () use (&$statusCalls): array {
                $statusCalls++;

                return [
                    'context' => ['slot' => 5],
                    'value' => [[
                        'slot' => 5,
                        'confirmations' => 3,
                        'confirmationStatus' => 'confirmed',
                        'err' => null,
                    ]],
                ];
            },
        ]);

        $payer = Keypair::generate();
        $recipient = Keypair::generate()->getPublicKey();

        $tx = new Transaction();
        $tx->addInstructions(SystemProgram::transfer($payer->getPublicKey(), $recipient, 1));

        $status = $this->container->get(Connection::class)->sendAndConfirmTransaction(
            $tx,
            [$payer],
            commitment: Commitment::confirmed(),
            timeoutSeconds: 5,
            pollIntervalMs: 10,
        );

        self::assertSame('confirmed', $status->confirmationStatus);
        self::assertSame(1, $sendCalls);
        self::assertGreaterThanOrEqual(1, $statusCalls);
    }

    #[Test]
    public function send_and_confirm_raises_when_blockhash_missing(): void
    {
        $this->fakeRpcByMethod([
            'getLatestBlockhash' => [
                'context' => ['slot' => 1],
                'value' => ['blockhash' => '', 'lastValidBlockHeight' => 200],
            ],
        ]);

        $this->expectException(GenericException::class);
        $this->expectExceptionMessageMatches('/recent blockhash/');

        $payer = Keypair::generate();
        $tx = new Transaction();
        $tx->addInstructions(SystemProgram::transfer($payer->getPublicKey(), $payer->getPublicKey(), 1));

        $this->container->get(Connection::class)
            ->sendAndConfirmTransaction($tx, [$payer], timeoutSeconds: 1, pollIntervalMs: 10);
    }
}
