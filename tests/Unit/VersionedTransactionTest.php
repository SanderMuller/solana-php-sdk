<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\AddressLookupTableAccount;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\MessageV0;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Transaction;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;
use SanderMuller\SolanaPhpSdk\Util\Buffer;
use SanderMuller\SolanaPhpSdk\Util\Signer;
use SanderMuller\SolanaPhpSdk\VersionedTransaction;

final class VersionedTransactionTest extends TestCase
{
    #[Test]
    public function compiles_v0_message_with_only_static_keys(): void
    {
        $payer = Keypair::generate();
        $recipient = Keypair::generate()->getPublicKey();
        $blockhash = Keypair::generate()->getPublicKey()->toBase58();

        $ix = SystemProgram::transfer($payer->getPublicKey(), $recipient, 1);

        $message = MessageV0::compile(
            payerKey: $payer->getPublicKey(),
            instructions: [$ix],
            recentBlockhash: $blockhash,
        );

        self::assertSame(0, $message->version());
        self::assertSame(1, $message->header()->numRequiredSignature);
        self::assertSame(0, $message->header()->numReadonlySignedAccounts);
        // System program + recipient are readonly non-signer / writable non-signer.
        self::assertSame(1, $message->header()->numReadonlyUnsignedAccounts);
        self::assertCount(3, $message->staticAccountKeys());
        self::assertCount(0, $message->addressTableLookups);
    }

    #[Test]
    public function v0_round_trip_serialize_and_deserialize(): void
    {
        $payer = Keypair::generate();
        $recipient = Keypair::generate()->getPublicKey();
        $blockhash = Keypair::generate()->getPublicKey()->toBase58();

        $ix = SystemProgram::transfer($payer->getPublicKey(), $recipient, 42);

        $message = MessageV0::compile(
            payerKey: $payer->getPublicKey(),
            instructions: [$ix],
            recentBlockhash: $blockhash,
        );

        $tx = new VersionedTransaction($message);
        $tx->sign($payer);

        $wire = $tx->serialize();
        $parsed = VersionedTransaction::deserialize($wire);

        self::assertSame(0, $parsed->message->version());
        self::assertSame($wire, $parsed->serialize());
        self::assertNotNull($parsed->signatures[0]);
        $sig = (string) $parsed->signatures[0];
        $pubKey = $payer->getPublicKey()->toBinaryString();
        self::assertNotSame('', $sig);
        self::assertNotSame('', $pubKey);
        self::assertTrue(
            sodium_crypto_sign_verify_detached($sig, $parsed->message->serialize(), $pubKey)
        );
    }

    #[Test]
    public function v0_message_pulls_keys_into_lookup_table(): void
    {
        $payer = Keypair::generate();
        $blockhash = Keypair::generate()->getPublicKey()->toBase58();

        $tableKey = Keypair::generate()->getPublicKey();
        $stored1 = Keypair::generate()->getPublicKey();
        $stored2 = Keypair::generate()->getPublicKey();

        $table = new AddressLookupTableAccount(
            key: $tableKey,
            addresses: [$stored1, $stored2],
        );

        $programId = Keypair::generate()->getPublicKey();
        $ix = new TransactionInstruction(
            programId: $programId,
            keys: [
                new AccountMeta($stored1, false, true),  // writable lookup
                new AccountMeta($stored2, false, false), // readonly lookup
            ],
            data: Buffer::fromArray([0xAA]),
        );

        $message = MessageV0::compile(
            payerKey: $payer->getPublicKey(),
            instructions: [$ix],
            recentBlockhash: $blockhash,
            addressLookupTableAccounts: [$table],
        );

        // Static keys: [payer, programId]. Lookup keys: writable=[stored1], readonly=[stored2].
        self::assertCount(2, $message->staticAccountKeys());
        self::assertCount(1, $message->addressTableLookups);
        self::assertTrue($message->addressTableLookups[0]->accountKey->equals($tableKey));
        self::assertSame([0], $message->addressTableLookups[0]->writableIndexes);
        self::assertSame([1], $message->addressTableLookups[0]->readonlyIndexes);
    }

    /**
     * Regression: `sign()` previously skipped non-`Keypair` signers and left a
     * zero-filled signature slot, producing transactions rejected on-chain.
     */
    #[Test]
    public function sign_with_signer_produces_valid_signature(): void
    {
        $payerKeypair = Keypair::generate();
        $payer = new Signer(
            publicKey: $payerKeypair->getPublicKey(),
            secretKey: $payerKeypair->getSecretKey(),
        );
        $recipient = Keypair::generate()->getPublicKey();
        $blockhash = Keypair::generate()->getPublicKey()->toBase58();

        $ix = SystemProgram::transfer($payer->getPublicKey(), $recipient, 7);

        $message = MessageV0::compile(
            payerKey: $payer->getPublicKey(),
            instructions: [$ix],
            recentBlockhash: $blockhash,
        );

        $tx = new VersionedTransaction($message);
        $tx->sign($payer);

        self::assertNotNull($tx->signatures[0]);
        self::assertNotSame(
            str_repeat("\0", 64),
            (string) $tx->signatures[0],
            'Signer path must produce a real signature, not a zero placeholder.',
        );
        $sig = (string) $tx->signatures[0];
        $pubKey = $payer->getPublicKey()->toBinaryString();
        self::assertNotSame('', $sig);
        self::assertNotSame('', $pubKey);
        self::assertTrue(sodium_crypto_sign_verify_detached($sig, $message->serialize(), $pubKey));
    }

    /**
     * Regression: `serialize()` previously wrote `count($signatures)` as the
     * vector prefix, so partially-signed multisig transactions emitted a short
     * prefix and the wire format collapsed.
     */
    #[Test]
    public function serialize_pads_missing_signatures_to_num_required(): void
    {
        // Two-of-two multisig: payer + cosigner are both required signers.
        $payer = Keypair::generate();
        $cosigner = Keypair::generate();
        $recipient = Keypair::generate()->getPublicKey();
        $blockhash = Keypair::generate()->getPublicKey()->toBase58();

        // Force the cosigner into the static keys as a writable signer by
        // adding a manual instruction that references it that way.
        $ix = new TransactionInstruction(
            programId: Keypair::generate()->getPublicKey(),
            keys: [
                new AccountMeta($payer->getPublicKey(), true, true),
                new AccountMeta($cosigner->getPublicKey(), true, true),
                new AccountMeta($recipient, false, true),
            ],
            data: Buffer::fromArray([0x01]),
        );

        $message = MessageV0::compile(
            payerKey: $payer->getPublicKey(),
            instructions: [$ix],
            recentBlockhash: $blockhash,
        );

        self::assertSame(2, $message->header()->numRequiredSignature);

        $tx = new VersionedTransaction($message);
        $tx->sign($payer); // only the payer signs — cosigner slot stays null

        $wire = $tx->serialize();
        $parsed = VersionedTransaction::deserialize($wire);

        // numRequired signatures must be present (one real, one zero-padded).
        self::assertCount(2, $parsed->signatures);
        self::assertNotNull($parsed->signatures[0]);
        self::assertNull($parsed->signatures[1]);
        self::assertSame($wire, $parsed->serialize());
    }

    #[Test]
    public function legacy_message_passes_through_versioned_transaction(): void
    {
        $sender = Keypair::fromSeed(array_fill(0, 32, 8));
        $recipient = PublicKey::from('J3dxNj7nDRRqRRXuEMynDG57DkZK4jYRuv3Garmb1i99');
        $blockhash = 'EETubP5AKHgjPAhzPAFcb8BAY1hMH639CWCFTqi3hq1k';

        $tx = new Transaction($blockhash, null, $sender->getPublicKey());
        $tx->addInstructions(SystemProgram::transfer($sender->getPublicKey(), $recipient, 49));
        $tx->sign($sender);

        $wire = $tx->serialize();
        $parsed = VersionedTransaction::deserialize($wire);

        self::assertNull($parsed->message->version()); // legacy
        self::assertSame($wire, $parsed->serialize());
    }
}
