<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\CompiledInstruction;
use Collectiq\SolanaPhpSdk\Util\HasPublicKey;
use Collectiq\SolanaPhpSdk\Util\MessageHeader;
use Collectiq\SolanaPhpSdk\Util\NonceInformation;
use Collectiq\SolanaPhpSdk\Util\ShortVec;
use Collectiq\SolanaPhpSdk\Util\SignaturePubKeyPair;
use Collectiq\SolanaPhpSdk\Util\Signer;
use Deprecated;
use SodiumException;

final class Transaction
{
    /**
     * Default (empty) signature
     *
     * Signatures are 64 bytes in length
     *
     * Buffer.alloc(64).fill(0);
     */
    private const array DEFAULT_SIGNATURE = [
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    ];

    public const int SIGNATURE_LENGTH = 64;

    private const int PACKET_DATA_SIZE = 1280 - 40 - 8;

    /**
     * @var array<TransactionInstruction>
     */
    public array $instructions = [];

    public function __construct(
        public ?string           $recentBlockhash = null,
        public ?NonceInformation $nonceInformation = null,
        public ?PublicKey        $feePayer = null,
        /**
         * @var array<SignaturePubKeyPair>
         */
        public array             $signatures = [],
    ) {}

    /**
     * The first (payer) Transaction signature
     */
    public function signature(): ?string
    {
        if ($this->signatures !== []) {
            return $this->signatures[0]->signature;
        }

        return null;
    }

    public function addInstructions(TransactionInstruction|self ...$items): Transaction
    {
        foreach ($items as $item) {
            if ($item instanceof TransactionInstruction) {
                $this->instructions[] = $item;
            } elseif ($item instanceof self) {
                $this->instructions = [
                    ...$this->instructions,
                    ...$item->instructions,
                ];
            }
        }

        return $this;
    }

    /**
     * Compile transaction data
     */
    public function compileMessage(): Message
    {
        $nonceInfo = $this->nonceInformation;

        if ($nonceInfo instanceof NonceInformation && count($this->instructions) && $this->instructions[0] !== $nonceInfo->nonceInstruction) {
            $this->recentBlockhash = $nonceInfo->nonce;
            array_unshift($this->instructions, $nonceInfo->nonceInstruction);
        }

        if (! $this->recentBlockhash) {
            throw new InputValidationException('Transaction recentBlockhash required.');
        }

        if ($this->instructions === []) {
            throw new InputValidationException('No instructions provided.');
        }

        if ($this->feePayer instanceof PublicKey) {
            $feePayer = $this->feePayer;
        } elseif (count($this->signatures) && $this->signatures[0]->getPublicKey()) {
            $feePayer = $this->signatures[0]->getPublicKey();
        } else {
            throw new InputValidationException('Transaction fee payer required.');
        }

        /**
         * @var array<string> $programIds
         */
        $programIds = [];

        /**
         * @var array<AccountMeta> $accountMetas
         */
        $accountMetas = [];

        foreach ($this->instructions as $i => $instruction) {
            if (! $instruction->programId) {
                throw new InputValidationException("Transaction instruction index {$i} has undefined program id.");
            }

            $accountMetas = [
                ...$accountMetas,
                ...$instruction->keys,
            ];

            $programId = $instruction->programId->toBase58();
            if (! in_array($programId, $programIds)) {
                $programIds[] = $programId;
            }
        }

        // Append programID account metas
        foreach ($programIds as $programId) {
            $accountMetas[] = new AccountMeta(
                publicKey: PublicKey::fromString($programId),
                isSigner: false,
                isWritable: false,
            );
        }

        // Sort. Prioritizing first by signer, then by writable
        usort($accountMetas, function (AccountMeta $x, AccountMeta $y): int {
            if ($x->isSigner !== $y->isSigner) {
                return $x->isSigner ? -1 : 1;
            }

            if ($x->isWritable !== $y->isWritable) {
                return $x->isWritable ? -1 : 1;
            }

            return 0;
        });

        // Cull duplicate account metas
        /**
         * @var array<AccountMeta> $uniqueMetas
         */
        $uniqueMetas = [];
        foreach ($accountMetas as $accountMeta) {
            $eachPublicKey = $accountMeta->getPublicKey();
            $uniqueIndex = self::arraySearchAccountMetaForPublicKey($uniqueMetas, $eachPublicKey);

            if ($uniqueIndex > -1) {
                $uniqueMetas[$uniqueIndex]->isWritable = $uniqueMetas[$uniqueIndex]->isWritable || $accountMeta->isWritable;
            } else {
                $uniqueMetas[] = $accountMeta;
            }
        }

        // Move fee payer to the front
        $feePayerIndex = self::arraySearchAccountMetaForPublicKey($uniqueMetas, $feePayer);
        if ($feePayerIndex > -1) {
            [$payerMeta] = array_splice($uniqueMetas, $feePayerIndex, 1);
            $payerMeta->isSigner = true;
            $payerMeta->isWritable = true;
            array_unshift($uniqueMetas, $payerMeta);
        } else {
            array_unshift($uniqueMetas, new AccountMeta($feePayer, true, true));
        }

        // Disallow unknown signers
        foreach ($this->signatures as $signature) {
            $uniqueIndex = self::arraySearchAccountMetaForPublicKey($uniqueMetas, $signature);
            if ($uniqueIndex > -1) {
                $uniqueMetas[$uniqueIndex]->isSigner = true;
            } else {
                throw new InputValidationException("Unknown signer: {$signature->getPublicKey()->toBase58()}");
            }
        }

        $numRequiredSignatures = 0;
        $numReadonlySignedAccounts = 0;
        $numReadonlyUnsignedAccounts = 0;

        // Split out signing from non-signing keys and count header values
        /**
         * @var array<string> $signedKeys
         */
        $signedKeys = [];

        /**
         * @var array<string> $unsignedKeys
         */
        $unsignedKeys = [];

        foreach ($uniqueMetas as $accountMeta) {
            if ($accountMeta->isSigner) {
                $signedKeys[] = $accountMeta->getPublicKey()->toBase58();
                $numRequiredSignatures++;
                if (! $accountMeta->isWritable) {
                    $numReadonlySignedAccounts++;
                }
            } else {
                $unsignedKeys[] = $accountMeta->getPublicKey()->toBase58();
                if (! $accountMeta->isWritable) {
                    $numReadonlyUnsignedAccounts++;
                }
            }
        }

        // Initialize signature array, if needed
        if (! $this->signatures) {
            $this->signatures = array_map(function (string $signedKey): SignaturePubKeyPair {
                return new SignaturePubKeyPair(PublicKey::fromString($signedKey), null);
            }, $signedKeys);
        }

        $accountKeys = [
            ...$signedKeys,
            ...$unsignedKeys,
        ];
        /**
         * @var array<CompiledInstruction> $instructions
         */
        $instructions = array_map(function (TransactionInstruction $instruction) use ($accountKeys): CompiledInstruction {
            $programIdIndex = array_search($instruction->programId->toBase58(), $accountKeys, true);

            $accounts = array_map(function (AccountMeta $meta) use ($accountKeys): int|string|false {
                return array_search($meta->getPublicKey()->toBase58(), $accountKeys, true);
            }, $instruction->keys);

            return new CompiledInstruction(
                programIdIndex: $programIdIndex,
                accounts: $accounts,
                data: $instruction->data,
            );
        }, $this->instructions);

        return new Message(
            header: new MessageHeader(
                $numRequiredSignatures,
                $numReadonlySignedAccounts,
                $numReadonlyUnsignedAccounts
            ),
            accountKeys: $accountKeys,
            recentBlockhash: $this->recentBlockhash,
            instructions: $instructions
        );
    }

    /**
     * Get a buffer of the Transaction data that need to be covered by signatures
     */
    public function serializeMessage(): string
    {
        return $this->compileMessage()->serialize();
    }

    /**
     * Specify the public keys which will be used to sign the Transaction.
     * The first signer will be used as the transaction fee payer account.
     *
     * Signatures can be added with either `partialSign` or `addSignature`
     *
     * @deprecated Deprecated since v0.84.0. Only the fee payer needs to be
     * specified and it can be set in the Transaction constructor or with the
     * `feePayer` property.
     */
    #[Deprecated(
        message: 'Only the fee payer needs to be specified and it can be set in the Transaction constructor or with the `feePayer` property.',
        since: '0.84.0',
    )]
    public function setSigners(PublicKey ...$signers): void
    {
        $uniqueSigners = $this->arrayUnique($signers);

        $this->signatures = array_map(function (PublicKey $signer): SignaturePubKeyPair {
            return new SignaturePubKeyPair($signer, null);
        }, $uniqueSigners);
    }

    /**
     * Fill in a signature for a partially signed Transaction.
     * The `signer` must be the corresponding `Keypair` for a `PublicKey` that was
     * previously provided to `signPartial`
     * @throws SodiumException
     */
    public function addSigner(Keypair $signer): void
    {
        $message = $this->compileMessage();
        $signData = $message->serialize();
        $signature = sodium_crypto_sign_detached($signData, $signer->getSecretKey()->toString());
        $this->_addSignature($signer->getPublicKey(), $signature);
    }

    /**
     * Sign the Transaction with the specified signers. Multiple signatures may
     * be applied to a Transaction. The first signature is considered "primary"
     * and is used identify and confirm transactions.
     *
     * If the Transaction `feePayer` is not set, the first signer will be used
     * as the transaction fee payer account.
     *
     * Transaction fields should not be modified after the first call to `sign`,
     * as doing so may invalidate the signature and cause the Transaction to be
     * rejected.
     *
     * The Transaction must be assigned a valid `recentBlockhash` before invoking this method
     *
     * @throws InputValidationException
     */
    public function sign(Signer|Keypair ...$signers): void
    {
        $this->partialSign(...$signers);
    }

    /**
     * Partially sign a transaction with the specified accounts. All accounts must
     * correspond to either the fee payer or a signer account in the transaction
     * instructions.
     *
     * All the caveats from the `sign` method apply to `partialSign`
     *
     * @throws GenericException
     * @throws \SodiumException
     * @throws InputValidationException
     */
    public function partialSign(Signer|Keypair ...$signers): void
    {
        // Dedupe signers
        $uniqueSigners = $this->arrayUnique($signers);

        $this->signatures = array_map(function ($signer): SignaturePubKeyPair {
            return new SignaturePubKeyPair(self::toPublicKey($signer), null);
        }, $uniqueSigners);

        $message = $this->compileMessage();
        $signData = $message->serialize();

        foreach ($uniqueSigners as $signer) {
            if ($signer instanceof Keypair) {
                $signature = sodium_crypto_sign_detached($signData, $signer->getSecretKey()->toString());
                if (strlen($signature) !== self::SIGNATURE_LENGTH) {
                    throw new InputValidationException('Signature has invalid length.');
                }

                $this->_addSignature(self::toPublicKey($signer), $signature);
            }
        }
    }

    /**
     * Add an externally created signature to a transaction. The public key
     * must correspond to either the fee payer or a signer account in the transaction
     * instructions.
     *
     * @throws GenericException
     * @throws InputValidationException
     */
    public function addSignature(PublicKey $publicKey, string $signature): void
    {
        if (strlen($signature) !== self::SIGNATURE_LENGTH) {
            throw new InputValidationException('Signature has invalid length.');
        }

//        $this->compile(); // Ensure signatures array is populated
        $this->_addSignature($publicKey, $signature);
    }

    /**
     * @throws InputValidationException
     */
    private function _addSignature(PublicKey $publicKey, string $signature): void
    {
        $indexOfPublicKey = self::arraySearchAccountMetaForPublicKey($this->signatures, $publicKey);

        if ($indexOfPublicKey === -1) {
            throw new InputValidationException("Unknown signer: {$publicKey->toBase58()}");
        }

        $this->signatures[$indexOfPublicKey]->signature = $signature;
    }

    public function verifySignatures(): bool
    {
        return $this->_verifySignature($this->serializeMessage(), true);
    }

    private function _verifySignature(string $signData, bool $requireAllSignatures): bool
    {
        foreach ($this->signatures as $signature) {
            if (! $signature->signature) {
                if ($requireAllSignatures) {
                    return false;
                }
            } elseif (! sodium_crypto_sign_verify_detached($signature->signature, $signData, $signature->getPublicKey()->toBinaryString())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Serialize the Transaction in the wire format.
     *
     * @param bool|null $requireAllSignature
     * @param bool|null $verifySignatures
     */
    public function serialize(bool $requireAllSignature = true, bool $verifySignatures = true): string
    {
        $signData = $this->serializeMessage();

        if ($verifySignatures && ! $this->_verifySignature($signData, $requireAllSignature)) {
            throw new GenericException('Signature verification failed');
        }

        return $this->_serialize($signData);
    }

    private function _serialize(string $signData): string
    {
        if (count($this->signatures) >= self::SIGNATURE_LENGTH * 4) {
            throw new InputValidationException('Too many signatures to encode.');
        }

        $signatureCount = ShortVec::encodeLength(count($this->signatures));

        $wireTransaction = Buffer::empty();
        // Encode signature count
        $wireTransaction->push($signatureCount);

        // Encode signatures
        foreach ($this->signatures as $signature) {
            if ($signature->signature && strlen($signature->signature) != self::SIGNATURE_LENGTH) {
                throw new GenericException("signature has invalid length: {$signature->signature}");
            }

            if ($sig = $signature->signature) {
                $wireTransaction->push($sig);
            } else {
                $wireTransaction->push(array_pad([], self::SIGNATURE_LENGTH, 0));
            }
        }

        // Encode signed data
        $wireTransaction->push($signData);

        if ($wireTransaction->length() > self::PACKET_DATA_SIZE) {
            $maxSize = self::PACKET_DATA_SIZE;

            throw new GenericException("transaction too large: {$wireTransaction->length()} > {$maxSize}");
        }

        return $wireTransaction->toString();
    }

    /**
     * Parse a wire transaction into a Transaction object.
     */
    public static function from(mixed $buffer): Transaction
    {
        $buffer = Buffer::from($buffer);

        [$signatureCount, $offset] = ShortVec::decodeLength($buffer);
        $signatures = [];
        for ($i = 0; $i < $signatureCount; $i++) {
            $signature = $buffer->slice($offset, self::SIGNATURE_LENGTH);
            $signatures[] = $signature->toBase58String();
            $offset += self::SIGNATURE_LENGTH;
        }

        $buffer = $buffer->slice($offset);

        return Transaction::populate(
            message: Message::from($buffer),
            signatures: $signatures,
        );
    }

    /**
     * Populate Transaction object from message and signatures
     *
     * @param string[] $signatures
     */
    public static function populate(Message $message, array $signatures): Transaction
    {
        $transaction = new Transaction();
        $transaction->recentBlockhash = $message->recentBlockhash;

        if ($message->header->numRequiredSignature > 0) {
            $transaction->feePayer = $message->accountKeys[0];
        }

        foreach ($signatures as $i => $signature) {
            $transaction->signatures[] = new SignaturePubKeyPair(
                $message->accountKeys[$i],
                $signature === Buffer::fromArray(self::DEFAULT_SIGNATURE)->toBase58String()
                    ? null
                    : Buffer::fromBase58($signature)->toString()
            );
        }

        foreach ($message->instructions as $instruction) {
            $keys = array_map(function (int $accountIndex) use ($transaction, $message): AccountMeta {
                $publicKey = $message->accountKeys[$accountIndex];
                $isSigner = self::arraySearchAccountMetaForPublicKey($transaction->signatures, $publicKey) !== -1
                    || $message->isAccountSigner($accountIndex);
                $isWritable = $message->isAccountWritable($accountIndex);

                return new AccountMeta($publicKey, $isSigner, $isWritable);
            }, $instruction->accounts);

            $transaction->instructions[] = new TransactionInstruction(
                programId: $message->accountKeys[$instruction->programIdIndex],
                keys: $keys,
                data: $instruction->data,
            );
        }

        return $transaction;
    }

    /**
     * @param AccountMeta[] $haystack
     */
    private static function arraySearchAccountMetaForPublicKey(array $haystack, HasPublicKey|string $needle): int|string
    {
        $publicKeyToSearchFor = self::toPublicKey($needle);

        foreach ($haystack as $i => $item) {
            if (self::toPublicKey($item) == $publicKeyToSearchFor) {
                return $i;
            }
        }

        return -1;
    }

    private function arrayUnique(array $haystack): array
    {
        $unique = [];
        foreach ($haystack as $item) {
            $indexOfSigner = self::arraySearchAccountMetaForPublicKey($unique, $item);

            if ($indexOfSigner === -1) {
                $unique[] = $item;
            }
        }

        return $unique;
    }

    private static function toPublicKey(HasPublicKey|string $fromKeypair): PublicKey
    {
        if ($fromKeypair instanceof HasPublicKey) {
            return $fromKeypair->getPublicKey();
        }

        return PublicKey::fromString($fromKeypair);
    }
}
