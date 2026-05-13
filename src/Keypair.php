<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use SanderMuller\SolanaPhpSdk\Util\Buffer;
use SanderMuller\SolanaPhpSdk\Util\HasPublicKey;
use SanderMuller\SolanaPhpSdk\Util\HasSecretKey;
use SodiumException;

/**
 * An account keypair used for signing transactions.
 */
final readonly class Keypair implements HasPublicKey, HasSecretKey
{
    public function __construct(
        public PublicKey $publicKey,
        public SecretKey $secretKey,
    ) {
        //
    }

    public static function generate(): Keypair
    {
        return self::fromSodiumKeypair(sodium_crypto_sign_keypair());
    }

    /**
     * @param non-empty-string $keypair Combined ed25519 keypair returned by libsodium
     *                                  ({@see sodium_crypto_sign_keypair()}).
     */
    public static function fromSodiumKeypair(string $keypair): Keypair
    {
        return new self(
            publicKey: PublicKey::from(sodium_crypto_sign_publickey($keypair)),
            secretKey: SecretKey::from(sodium_crypto_sign_secretkey($keypair)),
        );
    }

    /**
     * Create a keypair from a raw secret key byte array.
     *
     * This method should only be used to recreate a keypair from a previously
     * generated secret key. Generating keypairs from a random seed should be done
     * with the {@link Keypair.fromSeed} method.
     */
    /**
     * @param array<int, int>|Buffer|string $secretKey
     */
    public static function fromSecretKey(array|Buffer|string $secretKey): Keypair
    {
        $secretKey = SecretKey::from($secretKey);

        return new self(
            publicKey: $secretKey->getPublicKey(),
            secretKey: $secretKey,
        );
    }

    /**
     * Generate a keypair from a 32 byte seed.
     *
     * @param string|array<int, int> $seed
     */
    public static function fromSeed(string|array $seed): Keypair
    {
        $seed = Buffer::from($seed)->toString();
        assert($seed !== '');

        return self::fromSodiumKeypair(sodium_crypto_sign_seed_keypair($seed));
    }

    /**
     * The public key for this keypair
     */
    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }

    /**
     * The raw secret key for this keypair
     */
    public function getSecretKey(): SecretKey
    {
        return $this->secretKey;
    }

    /**
     * Sign a message with the keypair's secret key.
     *
     * @param string $message The message to sign.
     * @return string The detached signature.
     * @throws SodiumException
     */
    public function sign(string $message): string
    {
        $secret = $this->secretKey->toBinaryString();
        assert($secret !== '', 'SecretKey produced an empty binary string.');

        return sodium_crypto_sign_detached($message, $secret);
    }

    /**
     * Verify a signature against a message using the keypair's public key.
     *
     * @param string $message The message that was signed.
     * @param string $signature The signature to verify.
     * @return bool True if the signature is valid, false otherwise.
     */
    public function verify(string $message, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        $public = $this->publicKey->toBinaryString();
        assert($public !== '', 'PublicKey produced an empty binary string.');

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, $public);
        } catch (SodiumException) {
            return false;
        }
    }
}
