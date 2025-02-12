<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Exceptions\SolanaPhpSdkException;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\HasPublicKey;
use Collectiq\SolanaPhpSdk\Util\Stringable;
use Exception;
use ParagonIE_Sodium_Compat;
use RangeException;

final class PublicKey implements Stringable, HasPublicKey
{
    public static ?int $fixedLength = 32;

    private const int LENGTH = 32;

    private const int MAX_SEED_LENGTH = 32;

    private Buffer $buffer;

    public function __construct(mixed $bnORBase58String)
    {
        if (is_int($bnORBase58String)) {
            $this->buffer = Buffer::empty()->pad(self::LENGTH, $bnORBase58String);
        } elseif (is_string($bnORBase58String)) {
            // https://stackoverflow.com/questions/25343508/detect-if-string-is-binary
            $isBinaryString = preg_match('~[^\x20-\x7E\t\r\n]~', $bnORBase58String) > 0;
            // if not binary string already, assumed to be a base58 string.
            if ($isBinaryString) {
                $this->buffer = Buffer::fromString($bnORBase58String);
            } else {
                $this->buffer = Buffer::fromBase58($bnORBase58String);
            }
        } else {
            $this->buffer = Buffer::from($bnORBase58String);
        }

        if ($this->buffer->length() !== self::LENGTH) {
            throw new InputValidationException("Invalid public key input. Expected length 32. Found: {$this->buffer->length()}");
        }
    }

    public static function from(mixed $bnORBase58String): self
    {
        return new self($bnORBase58String);
    }

    public static function default(): self
    {
        return new self('11111111111111111111111111111111');
    }

    public static function generate(): self
    {
        return self::from(Buffer::alloc(32));
    }

    /**
     * Check if two publicKeys are equal
     */
    public function equals(self $publicKey): bool
    {
        return $this->buffer->equals($publicKey->buffer);
    }

    /**
     * Return the base-58 representation of the public key
     */
    public function toBase58(): string
    {
        return $this->buffer->toBase58String();
    }

    public function toBytes(): array
    {
        return $this->buffer->toBytes();
    }

    /**
     * Derive a public key from another key, a seed, and a program ID.
     * The program ID will also serve as the owner of the public key, giving
     * it permission to write data to the account.
     */
    public static function createWithSeed(self $fromPublicKey, string $seed, self $programId): self
    {
        $buffer = Buffer::empty()
            ->push($fromPublicKey)
            ->push($seed)
            ->push($programId);

        $binaryString = sodium_hex2bin(hash('sha256', $buffer->toString()));

        return self::from($binaryString);
    }

    /**
     * Derive a program address from seeds and a program ID.
     */
    public static function createProgramAddress(array $seeds, self $programId): self
    {
        $buffer = Buffer::empty();

        foreach ($seeds as $seed) {
            $seed = Buffer::from($seed);

            if ($seed->length() > self::MAX_SEED_LENGTH) {
                throw new InputValidationException('Max seed length exceeded.');
            }

            $buffer->push($seed);
        }

        $buffer->push($programId)->push('ProgramDerivedAddress');

        $binaryString = sodium_hex2bin(hash('sha256', $buffer->toString()));

        if (self::isOnCurve($binaryString)) {
            throw new InputValidationException('Invalid seeds, address must fall off the curve.');
        }

        return self::from($binaryString);
    }

    /**
     * @return array 2 elements, [0] = PublicKey, [1] = nonce
     * @throws SolanaPhpSdkException
     */
    public static function findProgramAddress(array $seeds, self $programId): array
    {
        $nonce = 255;

        while ($nonce !== 0) {
            try {
                $copyOfSeedsWithNonce = $seeds;
                $copyOfSeedsWithNonce[] = [$nonce];
                $address = self::createProgramAddress($copyOfSeedsWithNonce, $programId);
            } catch (Exception) {
                $nonce--;

                continue;
            }

            return [$address, $nonce];
        }

        throw new SolanaPhpSdkException('Unable to find a viable program address nonce.');
    }

    /**
     * @return array 2 elements, [0] = PublicKey, [1] = integer
     */
    public static function findProgramAddressSync(array $seeds, self $programId): array
    {
        return self::findProgramAddress($seeds, $programId);
    }

    /**
     * Check that a public key is on the ed25519 curve.
     */
    public static function isOnCurve(mixed $publicKey): bool
    {
        try {
            $binaryString = $publicKey instanceof self
                ? $publicKey->toBinaryString()
                : $publicKey;

            /**
             * Sodium extension method sometimes returns "conversion failed" exception.
             * $_ = sodium_crypto_sign_ed25519_pk_to_curve25519($binaryString);
             */
            $_ = ParagonIE_Sodium_Compat::crypto_sign_ed25519_pk_to_curve25519($binaryString);

            return true;
        } catch (RangeException|\SodiumException) {
            return false;
        }
    }

    public function getPublicKey(): self
    {
        return $this;
    }

    public function toBinaryString(): string
    {
        return $this->buffer->toBinaryString();
    }

    public function toString(): string
    {
        return $this->toBase58();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
