<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use SanderMuller\SolanaPhpSdk\Util\Buffer;
use SanderMuller\SolanaPhpSdk\Util\HasPublicKey;
use SanderMuller\SolanaPhpSdk\Util\HasSecretKey;

final readonly class Account implements HasPublicKey, HasSecretKey
{
    private Keypair $keypair;

    /**
     * @param array<int, int>|Buffer|null $secretKey
     */
    public function __construct(array|Buffer|null $secretKey = null)
    {
        if ($secretKey instanceof Buffer || (is_array($secretKey) && $secretKey !== [])) {
            $this->keypair = Keypair::fromSecretKey(
                Buffer::from($secretKey)->toString()
            );
        } else {
            $this->keypair = Keypair::generate();
        }
    }

    public function getPublicKey(): PublicKey
    {
        return $this->keypair->getPublicKey();
    }

    public function getSecretKey(): SecretKey
    {
        return $this->keypair->getSecretKey();
    }
}
