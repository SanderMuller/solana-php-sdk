<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Facades;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\DataObjects\AccountInfo;
use SanderMuller\SolanaPhpSdk\DataObjects\BlockhashInfo;
use SanderMuller\SolanaPhpSdk\DataObjects\SignatureStatus;
use SanderMuller\SolanaPhpSdk\Enum\Encoding;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;
use SanderMuller\SolanaPhpSdk\Testing\InMemoryRpcStub;
use SanderMuller\SolanaPhpSdk\Transaction;
use SanderMuller\SolanaPhpSdk\Util\Commitment;
use SanderMuller\SolanaPhpSdk\Util\Signer;

/**
 * Static accessor over the bound {@see Connection}.
 *
 * In tests, `Solana::fake()` rebinds the underlying {@see SolanaRpcClient}
 * to an {@see InMemoryRpcStub} so the SDK never reaches the network.
 *
 * @method static AccountInfo accountInfo((PublicKey|string) $walletAddress, Encoding|string $encoding = \SanderMuller\SolanaPhpSdk\Enum\Encoding::BASE64)
 * @method static float getBalance(string $walletAddress)
 * @method static BlockhashInfo latestBlockhash((Commitment|null) $commitment = null)
 * @method static SignatureStatus sendAndConfirmTransaction(Transaction $transaction, array<Keypair|Signer> $signers, array<string, mixed> $params = [], Commitment|null $commitment = null, int $timeoutSeconds = 60, int $pollIntervalMs = 500)
 *
 * @see Connection
 *
 * @api
 */
final class Solana extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Connection::class;
    }

    /**
     * Swap the bound {@see SolanaRpcClient} for an {@see InMemoryRpcStub}
     * and return the stub so the test can script responses + assert call
     * sequences. Tests hold the returned stub; nothing is cached
     * statically on the facade.
     */
    public static function fake(): InMemoryRpcStub
    {
        $stub = new InMemoryRpcStub();
        Container::getInstance()->instance(SolanaRpcClient::class, $stub->client());

        self::clearResolvedInstance(Connection::class);

        return $stub;
    }
}
