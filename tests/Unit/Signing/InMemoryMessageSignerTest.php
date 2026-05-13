<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Signing;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Contracts\MessageSigner;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Signing\InMemoryMessageSigner;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class InMemoryMessageSignerTest extends TestCase
{
    #[Test]
    public function in_memory_signer_implements_message_signer_contract(): void
    {
        $signer = InMemoryMessageSigner::fromKeypair(Keypair::generate());

        // Lock the public surface to the MessageSigner contract.
        $contracts = class_implements($signer);
        self::assertIsArray($contracts);
        self::assertContains(MessageSigner::class, $contracts);
        self::assertSame(32, strlen($signer->getPublicKey()->toBinaryString()));
    }

    #[Test]
    public function signs_message_with_keypair_secret(): void
    {
        $keypair = Keypair::generate();
        $signer = InMemoryMessageSigner::fromKeypair($keypair);

        $message = 'arbitrary message bytes';
        $signature = $signer->signMessage($message);

        self::assertSame(64, strlen($signature));
        self::assertTrue($keypair->verify($message, $signature));
    }

    #[Test]
    public function two_signers_for_same_keypair_produce_identical_signatures(): void
    {
        // Ed25519 is deterministic — same key + same message ⇒ same signature.
        // The interface contract permits a remote signer to break this property,
        // but in-memory must remain deterministic so callers can use it as a
        // ground-truth baseline in tests.
        $keypair = Keypair::generate();
        $a = InMemoryMessageSigner::fromKeypair($keypair);
        $b = InMemoryMessageSigner::fromKeypair($keypair);

        self::assertSame($a->signMessage('hello'), $b->signMessage('hello'));
    }
}
