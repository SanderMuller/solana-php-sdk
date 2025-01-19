<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\HasPublicKey;
use Collectiq\SolanaPhpSdk\Util\HasSecretKey;
use SodiumException;

/**
 * An account keypair used for signing transactions.
 *  @property PublicKey $publicKey The public key for this keypair
 *  @property PublicKey $secretKey The raw secret key for this keypair
 */
final readonly class Keypair implements HasPublicKey, HasSecretKey
{
    public PublicKey $publicKey;

    public SecretKey $secretKey;

    public function __construct(
        mixed $publicKey = null,
        mixed $secretKey = null,
    ) {
        if ($publicKey === null && $secretKey === null) {
            $keypair = sodium_crypto_sign_keypair();

            $publicKey = sodium_crypto_sign_publickey($keypair);
            $secretKey = sodium_crypto_sign_secretkey($keypair);
        }

        $this->publicKey = PublicKey::from($publicKey);
        $this->secretKey = SecretKey::from($secretKey);
    }

    /**
     * @throws SodiumException
     */
    public static function generate(): Keypair
    {
        $keypair = sodium_crypto_sign_keypair();

        return self::from($keypair);
    }

    /**
     * @throws SodiumException
     */
    public static function from(string $keypair): Keypair
    {
        return new self(
            sodium_crypto_sign_publickey($keypair),
            sodium_crypto_sign_secretkey($keypair)
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

        $publicKey = sodium_crypto_sign_publickey_from_secretkey($secretKey->toString());

        return new self(
            publicKey: PublicKey::fromString($publicKey),
            secretKey: $secretKey,
        );
    }

    /**
     * Generate a keypair from a 32 byte seed.
     *
     * @param string|array $seed
     * @throws SodiumException
     */
    public static function fromSeed($seed): Keypair
    {
        $seed = Buffer::from($seed)->toString();

        $keypair = sodium_crypto_sign_seed_keypair($seed);

        return self::from($keypair);
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
