<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Transaction;

final class MultiSignerSendTest extends TestCase
{
    #[Test]
    public function send_transaction_signs_with_every_signer_in_a_single_call(): void
    {
        // Regression guard. An earlier draft of `Connection::sendTransaction`
        // looped `$tx->sign($signer)` per signer; because `Transaction::sign`
        // delegates to `partialSign()` which rebuilds the signature vector
        // from only the signers passed in that call, the loop emitted a
        // 1-of-N transaction with only the last signer slot populated.
        //
        // After the fix, all signers must be passed in one `sign(...)` call
        // so every slot is filled and `verifySignatures()` round-trips.
        $payer = Keypair::generate();
        $cosigner = Keypair::generate();
        $recipient = Keypair::generate()->getPublicKey();

        $tx = new Transaction(
            recentBlockhash: '11111111111111111111111111111111',
            feePayer: $payer->getPublicKey(),
        );
        $tx->addInstructions(
            SystemProgram::transfer($payer->getPublicKey(), $recipient, 1),
            // Allocate seeds the cosigner into the writable+signer slot.
            SystemProgram::allocate($cosigner->getPublicKey(), 0),
        );

        $this->fakeRpcByMethod([
            'sendTransaction' => 'sigOK',
            'getLatestBlockhash' => [
                'context' => ['slot' => 1],
                'value' => ['blockhash' => '11111111111111111111111111111111', 'lastValidBlockHeight' => 250],
            ],
        ]);

        $this->container->get(Connection::class)
            ->sendTransaction($tx, signers: [$payer, $cosigner]);

        // Both slots filled ⇒ verifySignatures passes.
        self::assertTrue($tx->verifySignatures());
    }
}
