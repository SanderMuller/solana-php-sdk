<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use SanderMuller\SolanaPhpSdk\Exceptions\GenericException;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Util\Buffer;
use SanderMuller\SolanaPhpSdk\Util\ShortVec;
use SanderMuller\SolanaPhpSdk\Util\Signer;

/**
 * Envelope around a {@see VersionedMessage} (legacy or v0) with detached
 * ed25519 signatures. Provides sign/serialize/deserialize matching the
 * Solana wire format consumed by `sendTransaction`.
 */
final class VersionedTransaction
{
    public const int SIGNATURE_LENGTH = 64;

    /**
     * @param array<string|null> $signatures one binary 64-byte signature per
     *        required signer (in account-key order); null entries are placeholders
     *        zero-filled on the wire.
     */
    public function __construct(
        public VersionedMessage $message,
        public array $signatures = [],
    ) {
        if ($this->signatures === []) {
            $this->signatures = array_fill(
                0,
                $message->header()->numRequiredSignature,
                null,
            );
        }
    }

    /**
     * Add a signature for each provided Keypair. Each signer must correspond
     * to one of the required signer account keys (positions 0..numRequiredSig-1
     * in {@see VersionedMessage::staticAccountKeys()}).
     */
    public function sign(Signer|Keypair ...$signers): void
    {
        $signData = $this->message->serialize();
        $staticKeys = $this->message->staticAccountKeys();
        $numRequired = $this->message->header()->numRequiredSignature;

        foreach ($signers as $signer) {
            $signerKey = $signer instanceof Keypair
                ? $signer->getPublicKey()
                : $signer->getPublicKey();

            $index = null;
            for ($i = 0; $i < $numRequired; $i++) {
                if ($staticKeys->get($i)->equals($signerKey)) {
                    $index = $i;
                    break;
                }
            }

            if ($index === null) {
                throw new InputValidationException(
                    "Signer {$signerKey->toBase58()} is not a required signer for this message."
                );
            }

            $signature = sodium_crypto_sign_detached(
                $signData,
                $signer->getSecretKey()->toBinaryString(),
            );

            $this->addSignature($signerKey, $signature);
        }
    }

    public function addSignature(PublicKey $publicKey, string $signature): void
    {
        if (strlen($signature) !== self::SIGNATURE_LENGTH) {
            throw new InputValidationException('Signature has invalid length.');
        }

        $numRequired = $this->message->header()->numRequiredSignature;
        $staticKeys = $this->message->staticAccountKeys();

        for ($i = 0; $i < $numRequired; $i++) {
            if ($staticKeys->get($i)->equals($publicKey)) {
                $this->signatures[$i] = $signature;

                return;
            }
        }

        throw new InputValidationException("Unknown signer: {$publicKey->toBase58()}");
    }

    public function serialize(): string
    {
        $numRequired = $this->message->header()->numRequiredSignature;
        $signatures = $this->signatures + array_fill(0, $numRequired, null);

        $out = Buffer::empty();
        $out->push(ShortVec::encodeLength($numRequired));

        for ($i = 0; $i < $numRequired; $i++) {
            $signature = $signatures[$i] ?? null;
            if ($signature === null) {
                $out->push(array_fill(0, self::SIGNATURE_LENGTH, 0));

                continue;
            }

            if (strlen($signature) !== self::SIGNATURE_LENGTH) {
                throw new GenericException("Signature has invalid length: {$signature}");
            }

            $out->push($signature);
        }

        $out->push($this->message->serialize());

        return $out->toString();
    }

    public static function deserialize(Buffer|array|string $rawTransaction): self
    {
        if (is_string($rawTransaction)) {
            $rawTransaction = Buffer::fromString($rawTransaction);
        }

        $buffer = Buffer::from($rawTransaction);

        [$sigCount, $offset] = ShortVec::decodeLength($buffer);
        $signatures = [];
        for ($i = 0; $i < $sigCount; $i++) {
            $sig = $buffer->slice($offset, self::SIGNATURE_LENGTH);
            $bytes = $sig->toBinaryString();
            $signatures[] = $bytes === str_repeat("\0", self::SIGNATURE_LENGTH) ? null : $bytes;
            $offset += self::SIGNATURE_LENGTH;
        }

        $messageBuffer = $buffer->slice($offset);
        $first = $messageBuffer->toArray()[0] ?? null;

        if ($first === null) {
            throw new InputValidationException('Transaction message is empty.');
        }

        $message = ($first & 0x80) !== 0
            ? MessageV0::deserialize($messageBuffer)
            : Message::deserialize($messageBuffer);

        return new self($message, $signatures);
    }
}
