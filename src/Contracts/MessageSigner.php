<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Contracts;

use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Signer;

/**
 * Signs raw message bytes for a single Solana public key.
 *
 * Implementations have one job — given a serialized transaction message,
 * return the 64-byte Ed25519 detached signature. The secret material may
 * live anywhere: an in-memory `Keypair`, an HSM, an AWS / GCP / Vault KMS
 * key handle, a hardware wallet, a remote signer service. The SDK does
 * not assume the bytes are accessible from PHP.
 *
 * Implementers MUST return a 64-byte string and MUST not mutate the input.
 *
 * Compare with the legacy {@see Signer}
 * value-object, which holds the raw bytes; that type is fine for local
 * signing but cannot represent a KMS-backed key. New code should depend on
 * this interface so the same call site works against either backend.
 */
interface MessageSigner
{
    public function getPublicKey(): PublicKey;

    /**
     * @param string $message Raw bytes of the serialized transaction message.
     * @return string 64-byte Ed25519 detached signature.
     */
    public function signMessage(string $message): string;
}
