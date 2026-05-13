<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\DataObjects\BlockhashInfo;
use SanderMuller\SolanaPhpSdk\Exceptions\UnsanitizedTransactionException;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Signing\InMemoryMessageSigner;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\TransactionBuilder;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;

final class TransactionBuilderTest extends TestCase
{
    #[Test]
    public function builds_a_signed_transaction_via_fluent_api(): void
    {
        $payer = Keypair::generate();
        $recipient = Keypair::generate()->getPublicKey();

        $tx = TransactionBuilder::new()
            ->feePayer($payer->getPublicKey())
            ->recentBlockhash('11111111111111111111111111111111')
            ->addInstruction(SystemProgram::transfer($payer->getPublicKey(), $recipient, 1))
            ->addSigner($payer)
            ->build();

        self::assertSame($payer->getPublicKey()->toBase58(), $tx->feePayer?->toBase58());
        self::assertCount(1, $tx->instructions);
        self::assertNotNull($tx->signature());
    }

    #[Test]
    public function accepts_blockhash_info_dto(): void
    {
        $payer = Keypair::generate();
        $recipient = Keypair::generate()->getPublicKey();

        $tx = TransactionBuilder::new()
            ->feePayer($payer->getPublicKey())
            ->recentBlockhash(new BlockhashInfo('11111111111111111111111111111111', 250))
            ->addInstruction(SystemProgram::transfer($payer->getPublicKey(), $recipient, 1))
            ->addSigner($payer)
            ->build();

        self::assertSame('11111111111111111111111111111111', $tx->recentBlockhash);
    }

    #[Test]
    public function refuses_to_build_without_fee_payer(): void
    {
        try {
            TransactionBuilder::new()
                ->recentBlockhash('11111111111111111111111111111111')
                ->addInstruction(SystemProgram::transfer(
                    Keypair::generate()->getPublicKey(),
                    Keypair::generate()->getPublicKey(),
                    1,
                ))
                ->build();
            self::fail('expected exception');
        } catch (UnsanitizedTransactionException $unsanitizedTransactionException) {
            self::assertContains('feePayer() is required.', $unsanitizedTransactionException->reasons);
        }
    }

    #[Test]
    public function refuses_to_build_without_blockhash(): void
    {
        $payer = Keypair::generate();

        try {
            TransactionBuilder::new()
                ->feePayer($payer->getPublicKey())
                ->addInstruction(SystemProgram::transfer($payer->getPublicKey(), $payer->getPublicKey(), 1))
                ->addSigner($payer)
                ->build();
            self::fail('expected exception');
        } catch (UnsanitizedTransactionException $unsanitizedTransactionException) {
            self::assertContains('recentBlockhash() is required.', $unsanitizedTransactionException->reasons);
        }
    }

    #[Test]
    public function refuses_to_build_when_fee_payer_not_in_signers(): void
    {
        $payer = Keypair::generate();
        $someoneElse = Keypair::generate();

        try {
            TransactionBuilder::new()
                ->feePayer($payer->getPublicKey())
                ->recentBlockhash('11111111111111111111111111111111')
                ->addInstruction(SystemProgram::transfer($payer->getPublicKey(), $payer->getPublicKey(), 1))
                ->addSigner($someoneElse)
                ->build();
            self::fail('expected exception');
        } catch (UnsanitizedTransactionException $unsanitizedTransactionException) {
            $matched = array_filter($unsanitizedTransactionException->reasons, static fn (string $r): bool => str_contains($r, 'feePayer'));
            self::assertNotEmpty($matched);
        }
    }

    #[Test]
    public function refuses_to_build_when_signer_account_missing_keypair(): void
    {
        $payer = Keypair::generate();
        $authorityAccountWeForgot = Keypair::generate()->getPublicKey();

        // Hand-rolled instruction with an extra signer account that has no
        // corresponding keypair — the precise footgun this builder guards.
        $ix = new TransactionInstruction(
            programId: PublicKey::default(),
            keys: [
                new AccountMeta($payer->getPublicKey(), isSigner: true, isWritable: true),
                new AccountMeta($authorityAccountWeForgot, isSigner: true, isWritable: false),
            ],
            data: [0, 0, 0, 0],
        );

        try {
            TransactionBuilder::new()
                ->feePayer($payer->getPublicKey())
                ->recentBlockhash('11111111111111111111111111111111')
                ->addInstruction($ix)
                ->addSigner($payer)
                ->build();
            self::fail('expected exception');
        } catch (UnsanitizedTransactionException $unsanitizedTransactionException) {
            $matched = array_filter($unsanitizedTransactionException->reasons, static fn (string $r): bool => str_contains($r, $authorityAccountWeForgot->toBase58()));
            self::assertNotEmpty($matched);
        }
    }

    #[Test]
    public function refuses_to_build_on_conflicting_is_signer_flags(): void
    {
        $payer = Keypair::generate();
        $account = Keypair::generate()->getPublicKey();

        $ixA = new TransactionInstruction(
            programId: PublicKey::default(),
            keys: [new AccountMeta($account, isSigner: false, isWritable: true)],
            data: [0],
        );
        $ixB = new TransactionInstruction(
            programId: PublicKey::default(),
            keys: [new AccountMeta($account, isSigner: true, isWritable: true)],
            data: [0],
        );

        try {
            TransactionBuilder::new()
                ->feePayer($payer->getPublicKey())
                ->recentBlockhash('11111111111111111111111111111111')
                ->addInstructions($ixA, $ixB)
                ->addSigner($payer)
                ->build();
            self::fail('expected exception');
        } catch (UnsanitizedTransactionException $unsanitizedTransactionException) {
            $matched = array_filter($unsanitizedTransactionException->reasons, static fn (string $r): bool => str_contains($r, 'conflicting isSigner'));
            self::assertNotEmpty($matched);
        }
    }

    #[Test]
    public function surfaces_multiple_reasons_at_once(): void
    {
        try {
            TransactionBuilder::new()->build();
            self::fail('expected exception');
        } catch (UnsanitizedTransactionException $unsanitizedTransactionException) {
            self::assertGreaterThanOrEqual(2, count($unsanitizedTransactionException->reasons));
        }
    }

    #[Test]
    public function message_signer_signs_via_serialize_message_path(): void
    {
        // MessageSigner path must round-trip verifySignatures(), proving wire
        // compatibility with the legacy in-process signing pipeline.
        $payer = Keypair::generate();
        $signer = InMemoryMessageSigner::fromKeypair($payer);
        $recipient = Keypair::generate()->getPublicKey();

        $tx = TransactionBuilder::new()
            ->feePayer($payer->getPublicKey())
            ->recentBlockhash('11111111111111111111111111111111')
            ->addInstruction(SystemProgram::transfer($payer->getPublicKey(), $recipient, 1))
            ->addMessageSigner($signer)
            ->build();

        self::assertTrue($tx->verifySignatures());
    }
}
