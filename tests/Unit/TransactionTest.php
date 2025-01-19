<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Message;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Transaction;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\CompiledInstruction;
use Collectiq\SolanaPhpSdk\Util\MessageHeader;
use PHPUnit\Framework\Attributes\Test;

final class TransactionTest extends TestCase
{
    /**
     * Seeded from
     * https://github.com/solana-labs/solana-web3.js/blob/master/test/transaction.test.ts
     * on October 2nd, 2021
     */
    #[Test]
    public function account_keys_are_ordered(): void
    {
        $payer = Keypair::generate();
        $account2 = Keypair::generate();
        $account3 = Keypair::generate();
        $transaction = new Transaction(
            recentBlockhash: Keypair::generate()->getPublicKey()->toBase58(),
            feePayer: $payer->getPublicKey(),
        );
        $transaction->addInstructions(new TransactionInstruction(
            programId: Keypair::generate()->getPublicKey(),
            keys: [
                new AccountMeta($account3->getPublicKey(), true, false),
                new AccountMeta($payer->getPublicKey(), true, true),
                new AccountMeta($account2->getPublicKey(), true, true),
            ],
        ));

        $message = $transaction->compileMessage();
        $this->assertEquals($payer->getPublicKey(), $message->accountKeys[0]);
        $this->assertEquals($account2->getPublicKey(), $message->accountKeys[1]);
        $this->assertEquals($account3->getPublicKey(), $message->accountKeys[2]);
    }

    #[Test]
    public function payer_is_first_account_meta(): void
    {
        $payer = Keypair::generate();
        $other = Keypair::generate();
        $recentBlockhash = Keypair::generate()->getPublicKey()->toBase58();
        $programId = Keypair::generate()->getPublicKey();
        $transaction = new Transaction($recentBlockhash);

        $transaction->addInstructions(new TransactionInstruction(
            programId: $programId,
            keys: [
                new AccountMeta($other->getPublicKey(), true, true),
                new AccountMeta($payer->getPublicKey(), true, true),
            ],
        ));

        $transaction->sign($payer, $other);

        $message = $transaction->compileMessage();
        $this->assertEquals($payer->getPublicKey(), $message->accountKeys[0]);
        $this->assertEquals($other->getPublicKey(), $message->accountKeys[1]);
        $this->assertSame(2, $message->header->numRequiredSignature);
        $this->assertSame(0, $message->header->numReadonlySignedAccounts);
        $this->assertSame(1, $message->header->numReadonlyUnsignedAccounts);
    }

    #[Test]
    public function payer_is_writable(): void
    {
        $payer = Keypair::generate();
        $transaction = new Transaction(Keypair::generate()->getPublicKey()->toBase58());
        $transaction->addInstructions(new TransactionInstruction(
            programId: Keypair::generate()->getPublicKey(),
            keys: [
                new AccountMeta($payer->getPublicKey(), true, false),
            ],
        ));

        $transaction->sign($payer);

        $message = $transaction->compileMessage();
        $this->assertEquals($payer->getPublicKey(), $message->accountKeys[0]);
        $this->assertSame(1, $message->header->numRequiredSignature);
        $this->assertSame(0, $message->header->numReadonlySignedAccounts);
        $this->assertSame(1, $message->header->numReadonlyUnsignedAccounts);
    }

    #[Test]
    public function partialSign(): void
    {
        $account1 = Keypair::generate();
        $account2 = Keypair::generate();
        $recentBlockhash = $account1->getPublicKey()->toBase58(); // Fake recentBlockhash
        $transfer = SystemProgram::transfer($account1->getPublicKey(), $account2->getPublicKey(), 123);

        $partialTransaction = new Transaction($recentBlockhash);
        $partialTransaction->addInstructions($transfer);
        $partialTransaction->partialSign($account1, $account2->getPublicKey());

        $this->assertSame(Transaction::SIGNATURE_LENGTH, strlen((string) $partialTransaction->signature()));
        $this->assertCount(2, $partialTransaction->signatures);
        $this->assertNotNull($partialTransaction->signatures[0]->signature);
        $this->assertNull($partialTransaction->signatures[1]->signature);

        $partialTransaction->addSigner($account2);
        $this->assertNotNull($partialTransaction->signatures[0]->signature);
        $this->assertNotNull($partialTransaction->signatures[1]->signature);

        $expected = new Transaction($recentBlockhash);
        $expected->addInstructions($transfer);
        $expected->sign($account1, $account2);
        $this->assertEquals($expected, $partialTransaction);
    }

    #[Test]
    public function dedupe_setSigners(): void
    {
        $payer = Keypair::generate();
        $duplicate1 = $payer;
        $duplicate2 = $payer;
        $transaction = new Transaction(Keypair::generate()->getPublicKey()->toBase58());
        $transaction->addInstructions(new TransactionInstruction(
            Keypair::generate()->getPublicKey(),
            [
                new AccountMeta($duplicate1->getPublicKey(), true, true),
                new AccountMeta($payer->getPublicKey(), false, true),
                new AccountMeta($duplicate2->getPublicKey(), true, false),
            ]
        ));

        $transaction->setSigners(
            $payer->getPublicKey(),
            $duplicate1->getPublicKey(),
            $duplicate2->getPublicKey()
        );

        $this->assertCount(1, $transaction->signatures);
        $this->assertEquals($payer->getPublicKey(), $transaction->signatures[0]->getPublicKey());

        $message = $transaction->compileMessage();
        $this->assertEquals($payer->getPublicKey(), $message->accountKeys[0]);
        $this->assertSame(1, $message->header->numRequiredSignature);
        $this->assertSame(0, $message->header->numReadonlySignedAccounts);
        $this->assertSame(1, $message->header->numReadonlyUnsignedAccounts);
    }

    #[Test]
    public function dedupe_sign(): void
    {
        $payer = Keypair::generate();
        $duplicate1 = $payer;
        $duplicate2 = $payer;
        $recentBlockhash = Keypair::generate()->getPublicKey()->toBase58();
        $programId = Keypair::generate()->getPublicKey();

        $transaction = new Transaction($recentBlockhash);
        $transaction->addInstructions(new TransactionInstruction(
            programId: $programId,
            keys: [
                new AccountMeta($duplicate1->getPublicKey(), true, true),
                new AccountMeta($payer->getPublicKey(), false, true),
                new AccountMeta($duplicate2->getPublicKey(), true, false),
            ],
        ));

        $transaction->sign(
            $payer,
            $duplicate1,
            $duplicate2,
        );

        $this->assertCount(1, $transaction->signatures);
        $this->assertEquals($payer->getPublicKey(), $transaction->signatures[0]->getPublicKey());

        $message = $transaction->compileMessage();
        $this->assertEquals($payer->getPublicKey(), $message->accountKeys[0]);
        $this->assertSame(1, $message->header->numRequiredSignature);
        $this->assertSame(0, $message->header->numReadonlySignedAccounts);
        $this->assertSame(1, $message->header->numReadonlyUnsignedAccounts);
    }

    #[Test]
    public function transfer_signatures(): void
    {
        $account1 = Keypair::generate();
        $account2 = Keypair::generate();
        $recentBlockhash = $account1->getPublicKey()->toBase58(); // Fake recentBlockhash

        $transfer1 = SystemProgram::transfer($account1->getPublicKey(), $account2->getPublicKey(), 123);
        $transfer2 = SystemProgram::transfer($account2->getPublicKey(), $account1->getPublicKey(), 123);

        $orgTransaction = new Transaction($recentBlockhash);
        $orgTransaction->addInstructions($transfer1, $transfer2);
        $orgTransaction->sign($account1, $account2);

        $newTransaction = new Transaction($orgTransaction->recentBlockhash, null, null, $orgTransaction->signatures);
        $newTransaction->addInstructions($transfer1, $transfer2);

        $this->assertEquals($orgTransaction, $newTransaction);
    }

    #[Test]
    public function use_nonce(): void
    {
        Keypair::generate();
        $account2 = Keypair::generate();
        Keypair::generate();
        $account2->getPublicKey()->toBase58(); // Fake Nonce hash
        // TODO
        self::markTestSkipped('TODO once SystemProgram::nonceAdvance is implemented.');
    }

    #[Test]
    public function parse_wire_format_and_serialize(): void
    {
        $sender = Keypair::fromSeed([8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8]); // Arbitrary known account
        $recentBlockhash = 'EETubP5AKHgjPAhzPAFcb8BAY1hMH639CWCFTqi3hq1k'; // Arbitrary known recentBlockhash
        $recipient = PublicKey::fromString('J3dxNj7nDRRqRRXuEMynDG57DkZK4jYRuv3Garmb1i99'); // Arbitrary known public key

        $transfer = SystemProgram::transfer($sender->getPublicKey(), $recipient, 49);
        $expectedTransaction = new Transaction($recentBlockhash, null, $sender->getPublicKey());
        $expectedTransaction->addInstructions($transfer);
        $expectedTransaction->sign($sender);

        $wireTransaction = sodium_base642bin('AVuErQHaXv0SG0/PchunfxHKt8wMRfMZzqV0tkC5qO6owYxWU2v871AoWywGoFQr4z+q/7mE8lIufNl/kxj+nQ0BAAEDE5j2LG0aRXxRumpLXz29L2n8qTIWIY3ImX5Ba9F9k8r9Q5/Mtmcn8onFxt47xKj+XdXXd3C8j/FcPu7csUrz/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAxJrndgN4IFTxep3s6kO0ROug7bEsbx0xxuDkqEvwUusBAgIAAQwCAAAAMQAAAAAAAAA=', SODIUM_BASE64_VARIANT_ORIGINAL);
        $tx = Transaction::from($wireTransaction);

        $this->assertEquals($tx, $expectedTransaction);
        $this->assertSame($wireTransaction, $expectedTransaction->serialize());
    }

    #[Test]
    public function populate_transaction(): void
    {
        $recentBlockhash = PublicKey::fromInt(1, BufferType::INT);
        $message = new Message(
            header: new MessageHeader(2, 0, 3),
            accountKeys: [
                PublicKey::fromInt(1, BufferType::INT),
                PublicKey::fromInt(2, BufferType::INT),
                PublicKey::fromInt(3, BufferType::INT),
                PublicKey::fromInt(4, BufferType::INT),
                PublicKey::fromInt(5, BufferType::INT),
            ],
            recentBlockhash: $recentBlockhash->__toString(),
            instructions: [
                new CompiledInstruction(4, [1, 2, 3], Buffer::fromArray(array_pad([], 5, 9))),
            ],
        );

        $signatures = [
            Buffer::fromArray(array_pad([], 64, 1))->toBase58String(),
            Buffer::fromArray(array_pad([], 64, 2))->toBase58String(),
        ];

        $transaction = Transaction::populate($message, $signatures);
        $this->assertCount(1, $transaction->instructions);
        $this->assertCount(2, $transaction->signatures);
        $this->assertEquals($recentBlockhash, $transaction->recentBlockhash);
    }

    #[Test]
    public function serialize_unsigned_transaction(): void
    {
        $sender = Keypair::fromSeed([8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8]); // Arbitrary known account
        $recentBlockhash = 'EETubP5AKHgjPAhzPAFcb8BAY1hMH639CWCFTqi3hq1k'; // Arbitrary known recentBlockhash
        $recipient = PublicKey::fromString('J3dxNj7nDRRqRRXuEMynDG57DkZK4jYRuv3Garmb1i99'); // Arbitrary known public key

        $transfer = SystemProgram::transfer($sender->getPublicKey(), $recipient, 49);
        $expectedTransaction = new Transaction($recentBlockhash, null, $sender->getPublicKey());
        $expectedTransaction->addInstructions($transfer);

        $this->assertCount(0, $expectedTransaction->signatures);
        $expectedTransaction->feePayer = $sender->getPublicKey();

        // Serializing without signatures is allowed if sigverify disabled.
        $expectedTransaction->serialize(true, false); // no exception
        // Serializing the message is allowed when signature array has null signatures
        $expectedTransaction->serializeMessage(); // no exception

        $expectedTransaction->feePayer = null;
//        $expectedTransaction->setSigners($sender->getPublicKey());
        $this->assertCount(1, $expectedTransaction->signatures);

        // Serializing without signatures is allowed if sigverify disabled.
        $expectedTransaction->serialize(true, false); // no exception
        // Serializing the message is allowed when signature array has null signatures
        $expectedTransaction->serializeMessage(); // no exception

        $expectedSerializationWithNoSignatures = sodium_base642bin('AQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAAEDE5j2LG0aRXxRumpLXz29L2n8qTIWIY3ImX5Ba9F9k8r9Q5/Mtmcn8onFxt47xKj+XdXXd3C8j/FcPu7csUrz/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAxJrndgN4IFTxep3s6kO0ROug7bEsbx0xxuDkqEvwUusBAgIAAQwCAAAAMQAAAAAAAAA=', SODIUM_BASE64_VARIANT_ORIGINAL);
        $this->assertSame($expectedSerializationWithNoSignatures, $expectedTransaction->serialize(false));

        // Properly signed transaction succeeds
        $expectedTransaction->partialSign($sender);
        $this->assertCount(1, $expectedTransaction->signatures);
        $expectedSerialization = sodium_base642bin('AVuErQHaXv0SG0/PchunfxHKt8wMRfMZzqV0tkC5qO6owYxWU2v871AoWywGoFQr4z+q/7mE8lIufNl/kxj+nQ0BAAEDE5j2LG0aRXxRumpLXz29L2n8qTIWIY3ImX5Ba9F9k8r9Q5/Mtmcn8onFxt47xKj+XdXXd3C8j/FcPu7csUrz/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAxJrndgN4IFTxep3s6kO0ROug7bEsbx0xxuDkqEvwUusBAgIAAQwCAAAAMQAAAAAAAAA=', SODIUM_BASE64_VARIANT_ORIGINAL);

        $this->assertSame($expectedSerialization, $expectedTransaction->serialize());
        $this->assertCount(1, $expectedTransaction->signatures);
    }

    #[Test]
    public function externally_signed_stake_delegate(): void
    {
//        $authority = Keypair::fromSeed(array_pad([], 32, 1));
//        $stake = PublicKey::fromInt(2, BufferType::INT),
//        $recentBlockhash = PublicKey::fromInt(3, BufferType::INT),
//        $vote = PublicKey::fromInt(4, BufferType::INT),

        self::markTestSkipped('TODO once StakeProgram is implemented');
    }
}
