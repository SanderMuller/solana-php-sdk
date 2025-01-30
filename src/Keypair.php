<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\HasPublicKey;
use Collectiq\SolanaPhpSdk\Util\HasSecretKey;

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
     */
    public static function fromSeed(string|array $seed): Keypair
    {
        $seed = Buffer::from($seed)->toString();

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
}
