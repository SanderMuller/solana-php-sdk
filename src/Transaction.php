<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use Deprecated;
use SanderMuller\SolanaPhpSdk\Exceptions\GenericException;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Support\PublicKeyCollection;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;
use SanderMuller\SolanaPhpSdk\Util\Buffer;
use SanderMuller\SolanaPhpSdk\Util\CompiledInstruction;
use SanderMuller\SolanaPhpSdk\Util\HasPublicKey;
use SanderMuller\SolanaPhpSdk\Util\MessageHeader;
use SanderMuller\SolanaPhpSdk\Util\NonceInformation;
use SanderMuller\SolanaPhpSdk\Util\ShortVec;
use SanderMuller\SolanaPhpSdk\Util\SignaturePubKeyPair;
use SanderMuller\SolanaPhpSdk\Util\Signer;
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
        public string|PublicKey|null $recentBlockhash = null,
        public ?NonceInformation     $nonceInformation = null,
        public ?PublicKey            $feePayer = null,
        /**
         * @var SignaturePubKeyPair[]
         */
        public array                 $signatures = [],
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
        $this->applyNonce();

        $recentBlockhash = $this->recentBlockhash;
        if ($recentBlockhash === null) {
            throw new InputValidationException('Transaction recentBlockhash required.');
        }

        if ($this->instructions === []) {
            throw new InputValidationException('No instructions provided.');
        }

        $feePayer = $this->resolveFeePayer();
        $accountMetas = $this->collectAccountMetas($this->instructions);
        $uniqueMetas = $this->dedupeAndSortMetas($accountMetas, $feePayer);
        $this->stampKnownSigners($uniqueMetas);

        [$signedKeys, $unsignedKeys, $header] = $this->splitMetas($uniqueMetas);

        if ($this->signatures === []) {
            $this->signatures = $this->placeholderSignaturesFor($signedKeys);
        }

        $accountKeys = $signedKeys->push(...$unsignedKeys);

        $instructions = $this->compileInstructions($this->instructions, $accountKeys);

        return new Message(
            header: $header,
            accountKeys: $accountKeys,
            recentBlockhash: $recentBlockhash,
            instructions: $instructions,
        );
    }

    /**
     * If the transaction is nonce-anchored, replace the recent blockhash with
     * the nonce value and prepend the nonceAdvance instruction.
     */
    private function applyNonce(): void
    {
        $nonceInfo = $this->nonceInformation;

        if (! $nonceInfo instanceof NonceInformation) {
            return;
        }

        if ($this->instructions === [] || $this->instructions[0] === $nonceInfo->nonceInstruction) {
            return;
        }

        $this->recentBlockhash = $nonceInfo->nonce;
        array_unshift($this->instructions, $nonceInfo->nonceInstruction);
    }

    private function resolveFeePayer(): PublicKey
    {
        if ($this->feePayer instanceof PublicKey) {
            return $this->feePayer;
        }

        if ($this->signatures !== [] && $this->signatures[0]->getPublicKey() instanceof PublicKey) {
            return $this->signatures[0]->getPublicKey();
        }

        throw new InputValidationException('Transaction fee payer required.');
    }

    /**
     * @param array<TransactionInstruction> $instructions
     * @return array<AccountMeta>
     */
    private function collectAccountMetas(array $instructions): array
    {
        /** @var array<AccountMeta> $accountMetas */
        $accountMetas = [];

        /** @var array<string, true> $programIdsSeen */
        $programIdsSeen = [];

        foreach ($instructions as $instruction) {
            foreach ($instruction->keys as $key) {
                $accountMetas[] = $key;
            }

            $programIdKey = $instruction->programId->toBase58();
            if (! isset($programIdsSeen[$programIdKey])) {
                $programIdsSeen[$programIdKey] = true;
                $accountMetas[] = new AccountMeta($instruction->programId, false, false);
            }
        }

        return $accountMetas;
    }

    /**
     * Sort by signer-first then writable-first, deduplicate by public key, and
     * place $feePayer at index 0 forced to signer + writable.
     *
     * @param array<AccountMeta> $accountMetas
     * @return array<AccountMeta>
     */
    private function dedupeAndSortMetas(array $accountMetas, PublicKey $feePayer): array
    {
        usort($accountMetas, static function (AccountMeta $x, AccountMeta $y): int {
            if ($x->isSigner !== $y->isSigner) {
                return $x->isSigner ? -1 : 1;
            }

            if ($x->isWritable !== $y->isWritable) {
                return $x->isWritable ? -1 : 1;
            }

            return 0;
        });

        /** @var array<AccountMeta> $uniqueMetas */
        $uniqueMetas = [];
        foreach ($accountMetas as $accountMeta) {
            $uniqueIndex = self::arraySearchAccountMetaForPublicKey($uniqueMetas, $accountMeta->getPublicKey());

            if (is_int($uniqueIndex) && $uniqueIndex > -1) {
                $uniqueMetas[$uniqueIndex]->isWritable = $uniqueMetas[$uniqueIndex]->isWritable || $accountMeta->isWritable;
            } else {
                $uniqueMetas[] = $accountMeta;
            }
        }

        $feePayerIndex = self::arraySearchAccountMetaForPublicKey($uniqueMetas, $feePayer);
        if (is_int($feePayerIndex) && $feePayerIndex > -1) {
            [$payerMeta] = array_splice($uniqueMetas, $feePayerIndex, 1);
            $payerMeta->isSigner = true;
            $payerMeta->isWritable = true;
            array_unshift($uniqueMetas, $payerMeta);
        } else {
            array_unshift($uniqueMetas, new AccountMeta($feePayer, true, true));
        }

        return $uniqueMetas;
    }

    /**
     * Flag the slots matching pre-recorded signatures as signers, rejecting
     * any signature whose key is absent from the message.
     *
     * @param array<AccountMeta> $uniqueMetas
     */
    private function stampKnownSigners(array $uniqueMetas): void
    {
        foreach ($this->signatures as $signature) {
            $uniqueIndex = self::arraySearchAccountMetaForPublicKey($uniqueMetas, $signature);
            if (is_int($uniqueIndex) && $uniqueIndex > -1) {
                $uniqueMetas[$uniqueIndex]->isSigner = true;
            } else {
                throw new InputValidationException("Unknown signer: {$signature->getPublicKey()->toBase58()}");
            }
        }
    }

    /**
     * Split account metas into signed/unsigned collections and compute the
     * matching {@see MessageHeader} counts.
     *
     * @param array<AccountMeta> $uniqueMetas
     * @return array{0: PublicKeyCollection, 1: PublicKeyCollection, 2: MessageHeader}
     */
    private function splitMetas(array $uniqueMetas): array
    {
        $numRequiredSignatures = 0;
        $numReadonlySignedAccounts = 0;
        $numReadonlyUnsignedAccounts = 0;

        $signedKeys = PublicKeyCollection::empty();
        $unsignedKeys = PublicKeyCollection::empty();

        foreach ($uniqueMetas as $accountMeta) {
            if ($accountMeta->isSigner) {
                $signedKeys->push($accountMeta->getPublicKey());
                $numRequiredSignatures++;
                if (! $accountMeta->isWritable) {
                    $numReadonlySignedAccounts++;
                }
            } else {
                $unsignedKeys->push($accountMeta->getPublicKey());
                if (! $accountMeta->isWritable) {
                    $numReadonlyUnsignedAccounts++;
                }
            }
        }

        return [
            $signedKeys,
            $unsignedKeys,
            new MessageHeader($numRequiredSignatures, $numReadonlySignedAccounts, $numReadonlyUnsignedAccounts),
        ];
    }

    /**
     * @return array<SignaturePubKeyPair>
     */
    private function placeholderSignaturesFor(PublicKeyCollection $signedKeys): array
    {
        $pairs = [];
        foreach ($signedKeys as $signedKey) {
            $pairs[] = new SignaturePubKeyPair($signedKey);
        }

        return $pairs;
    }

    /**
     * @param array<TransactionInstruction> $instructions
     * @return array<CompiledInstruction>
     */
    private function compileInstructions(array $instructions, PublicKeyCollection $accountKeys): array
    {
        $compiled = [];
        foreach ($instructions as $instruction) {
            $programIdIndex = $accountKeys->search(
                static fn (PublicKey $publicKey): bool => $instruction->programId->equals($publicKey),
                true,
            );

            if (! is_int($programIdIndex)) {
                throw new InputValidationException(
                    "Compiled instruction missing program id index for {$instruction->programId->toBase58()}.",
                );
            }

            $accounts = [];
            foreach ($instruction->keys as $meta) {
                $metaPublicKey = $meta->getPublicKey();
                $index = $accountKeys->search(
                    static fn (PublicKey $publicKey): bool => $metaPublicKey->equals($publicKey),
                    true,
                );

                if (! is_int($index)) {
                    throw new InputValidationException(
                        "Compiled instruction missing account index for {$metaPublicKey->toBase58()}.",
                    );
                }

                $accounts[] = $index;
            }

            $compiled[] = new CompiledInstruction(
                programIdIndex: $programIdIndex,
                accounts: $accounts,
                data: $instruction->data,
            );
        }

        return $compiled;
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
     */
    #[Deprecated(
        message: 'Only the fee payer needs to be specified and it can be set in the Transaction constructor or with the `feePayer` property.',
        since: '0.84.0',
    )]
    public function setSigners(PublicKey ...$signers): void
    {
        $uniqueSigners = $this->arrayUnique($signers);

        $pairs = [];
        foreach ($uniqueSigners as $signer) {
            $pairs[] = new SignaturePubKeyPair(self::toPublicKey($signer));
        }

        $this->signatures = $pairs;
    }

    /**
     * Fill in a signature for a partially signed Transaction.
     * The `signer` must be the corresponding `Keypair` for a `PublicKey` that was
     * previously provided to `signPartial`
     * @throws SodiumException|InputValidationException
     */
    public function addSigner(Keypair $signer): void
    {
        $message = $this->compileMessage();
        $signData = $message->serialize();

        $secret = $signer->getSecretKey()->toString();
        assert($secret !== '', 'Signer secret key is empty.');

        $signature = sodium_crypto_sign_detached($signData, $secret);
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
     * @throws InputValidationException|SodiumException
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
     * @throws SodiumException
     * @throws InputValidationException
     */
    public function partialSign(Signer|Keypair|PublicKey ...$signers): void
    {
        // Dedupe signers
        $uniqueSigners = $this->arrayUnique($signers);

        $pairs = [];
        foreach ($uniqueSigners as $signer) {
            $pairs[] = new SignaturePubKeyPair(self::toPublicKey($signer));
        }

        $this->signatures = $pairs;

        $message = $this->compileMessage();
        $signData = $message->serialize();

        foreach ($uniqueSigners as $signer) {
            $secret = match (true) {
                $signer instanceof Keypair => $signer->getSecretKey()->toString(),
                $signer instanceof Signer => $signer->getSecretKey()->toBinaryString(),
                default => null,
            };

            if ($secret === null) {
                // Pure PublicKey: caller will supply the signature later via addSignature().
                continue;
            }

            assert($secret !== '', 'Signer produced an empty secret key.');
            $signature = sodium_crypto_sign_detached($signData, $secret);
            if (strlen($signature) !== self::SIGNATURE_LENGTH) {
                throw new InputValidationException('Signature has invalid length.');
            }

            $this->_addSignature(self::toPublicKey($signer), $signature);
        }
    }

    /**
     * Add an externally created signature to a transaction. The public key
     * must correspond to either the fee payer or a signer account in the transaction
     * instructions.
     *
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

        if (! is_int($indexOfPublicKey) || $indexOfPublicKey === -1) {
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
            if ($signature->signature === null || $signature->signature === '') {
                if ($requireAllSignatures) {
                    return false;
                }

                continue;
            }

            $public = $signature->getPublicKey()->toBinaryString();
            assert($public !== '', 'Signer public key produced an empty binary string.');

            if (! sodium_crypto_sign_verify_detached((string) $signature->signature, $signData, $public)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Serialize the Transaction in the wire format.
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
            $sig = $signature->signature;

            if ($sig !== null && $sig !== '') {
                if (strlen($sig) !== self::SIGNATURE_LENGTH) {
                    throw new GenericException("signature has invalid length: {$sig}");
                }

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
        $transaction->recentBlockhash = $message->recentBlockhash instanceof PublicKey
            ? $message->recentBlockhash->toBase58()
            : $message->recentBlockhash;

        if ($message->header->numRequiredSignature > 0) {
            $transaction->feePayer = self::accountKeyAt($message, 0);
        }

        foreach ($signatures as $i => $signature) {
            $transaction->signatures[] = new SignaturePubKeyPair(
                self::accountKeyAt($message, $i),
                $signature === Buffer::fromArray(self::DEFAULT_SIGNATURE)->toBase58String()
                    ? null
                    : Buffer::fromBase58($signature)->toString()
            );
        }

        foreach ($message->instructions as $instruction) {
            $keys = array_map(static function (int $accountIndex) use ($transaction, $message): AccountMeta {
                $publicKey = self::accountKeyAt($message, $accountIndex);
                $isSigner = self::arraySearchAccountMetaForPublicKey($transaction->signatures, $publicKey) !== -1
                    || $message->isAccountSigner($accountIndex);
                $isWritable = $message->isAccountWritable($accountIndex);

                return new AccountMeta($publicKey, $isSigner, $isWritable);
            }, $instruction->accounts);

            $transaction->instructions[] = new TransactionInstruction(
                programId: self::accountKeyAt($message, $instruction->programIdIndex),
                keys: $keys,
                data: $instruction->data,
            );
        }

        return $transaction;
    }

    /**
     * @param array<HasPublicKey|string> $haystack
     */
    private static function arraySearchAccountMetaForPublicKey(array $haystack, HasPublicKey|string $needle): int|string
    {
        $publicKeyToSearchFor = self::toPublicKey($needle);

        foreach ($haystack as $i => $item) {
            if (self::toPublicKey($item)->equals($publicKeyToSearchFor)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @template T of HasPublicKey|string
     * @param array<T> $haystack
     * @return array<T>
     */
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

        return PublicKey::from($fromKeypair);
    }

    /**
     * Resolve a {@see PublicKey} from {@see Message::$accountKeys} at $index,
     * throwing when the index is out of range. Use this in place of raw
     * `$accountKeys->offsetGet(...)` so the `PublicKey|null` collection
     * generic does not bleed into call sites that require a non-null key.
     */
    private static function accountKeyAt(Message $message, int $index): PublicKey
    {
        $key = $message->accountKeys->get($index);

        if (! $key instanceof PublicKey) {
            throw new InputValidationException("Account key index {$index} is out of range.");
        }

        return $key;
    }
}
