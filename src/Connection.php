<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Programs\IsProgram;
use Collectiq\SolanaPhpSdk\Programs\Program;
use Collectiq\SolanaPhpSdk\Util\Commitment;
use Illuminate\Http\Client\Response;

/**
 * Class Connection
 */
final class Connection implements Program
{
    use IsProgram;

    public function getAccountInfo(string|PublicKey $walletAddress): array
    {
        $accountResponse = $this->client->call('getAccountInfo', [
            (string) $walletAddress, ['encoding' => 'base64'],
        ])['value'];

        if (! $accountResponse) {
            throw new AccountNotFoundException("API Error: Account {$walletAddress} not found.");
        }

        return $accountResponse;
    }

    public function getBalance(string $walletAddress): float
    {
        return $this->client->call('getBalance', [$walletAddress])['value'];
    }

    public function getTransaction(string $transactionSignature, ?Commitment $commitment = null): ?array
    {
        return $this->client->call('getTransaction', [
            $transactionSignature,
            [
                'commitment' => $commitment,
            ],
        ]);
    }

    public function getLatestBlockhash(?Commitment $commitment = null): array
    {
        return $this->client->call('getLatestBlockhash', array_filter([$commitment]))['value'];
    }

    /**
     * @param Keypair[] $signers
     * TODO Add Support for Versioned TXns
     */
    public function sendTransaction(Transaction $transaction, array $signers, array $params = []): mixed
    {
        if (! $transaction->recentBlockhash) {
            $transaction->recentBlockhash = $this->getLatestBlockhash()['blockhash'];
        }

        foreach ($signers as $signer) {
            $transaction->sign($signer);
        }

        $rawBinaryString = $transaction->serialize(false);

        $hashString = sodium_bin2base64($rawBinaryString, SODIUM_BASE64_VARIANT_ORIGINAL);

        $send_params = ['encoding' => 'base64', 'preflightCommitment' => 'confirmed'];

        foreach ($params as $k => $v) {
            $send_params[$k] = $v;
        }

        return $this->client->call('sendTransaction', [$hashString, $send_params]);
    }

    /**
     * @param Keypair[] $signers
     */
    public function simulateTransaction(Transaction $transaction, array $signers, array $params = []): array|Response
    {
        foreach ($signers as $signer) {
            $transaction->sign($signer);
        }

        $rawBinaryString = $transaction->serialize(false);

        $hashString = sodium_bin2base64($rawBinaryString, SODIUM_BASE64_VARIANT_ORIGINAL);

        $send_params = ['encoding' => 'base64', 'commitment' => 'confirmed', 'sigVerify' => true];

        foreach ($params as $k => $v) {
            $send_params[$k] = $v;
        }

        return $this->client->call('simulateTransaction', [$hashString, $send_params]);
    }

    public function requestAirdrop(array $params = []): string
    {
        return $this->client->call('requestAirdrop', $params);
    }

    // https://solana.com/docs/rpc/http/getprogramaccounts
    // https://sns.guide/domain-name/all-domains.html
    public function getProgramAccounts(string $programIdBs58, $dataSlice, $filters): mixed
    {
        $params = [
            $programIdBs58,
            [
                'dataSlice' => $dataSlice,
                'filters' => $filters,
                'dataSize' => 108, // 'dataSize' => 108
                'encoding' => 'base64',
                'page' => 1,
                'limit' => 1000,
            ],
        ];

        return $this->client->call('getProgramAccounts', $params);
        // return $this->client->call('getAssetsByOwner', $params );
    }

    public function getMinimumBalanceForRentExemption(int $space = 1024): int
    {
        return $this->client->call('getMinimumBalanceForRentExemption', [$space]);
    }
}
