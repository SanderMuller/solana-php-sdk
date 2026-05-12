<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\DataObjects\SignatureStatus;
use Collectiq\SolanaPhpSdk\DataObjects\TransactionStatement;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Programs\IsProgram;
use Collectiq\SolanaPhpSdk\Programs\Program;
use Collectiq\SolanaPhpSdk\Util\Commitment;
use Collectiq\SolanaPhpSdk\Util\Signer;

/**
 * Class Connection
 */
final class Connection implements Program
{
    use IsProgram;

    /**
     * @return array<string, mixed>
     */
    public function getAccountInfo(string|PublicKey $walletAddress): array
    {
        $response = $this->client->call('getAccountInfo', [
            (string) $walletAddress, ['encoding' => 'base64'],
        ]);

        $accountResponse = is_array($response) ? ($response['value'] ?? null) : null;

        if (! is_array($accountResponse)) {
            throw new AccountNotFoundException("API Error: Account {$walletAddress} not found.");
        }

        /** @var array<string, mixed> $accountResponse */
        return $accountResponse;
    }

    public function getBalance(string $walletAddress): float
    {
        $response = $this->client->call('getBalance', [$walletAddress]);

        if (! is_array($response)) {
            return 0.0;
        }

        $value = $response['value'] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function getTransaction(string $transactionSignature, ?Commitment $commitment = null): ?TransactionStatement
    {
        $response = $this->client->call('getTransaction', [
            $transactionSignature,
            [
                'encoding' => 'jsonParsed',
                'commitment' => $commitment,
            ],
        ]);

        if (! is_array($response)) {
            return null;
        }

        /** @var array<string, mixed> $response */
        return TransactionStatement::fromResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLatestBlockhash(?Commitment $commitment = null): array
    {
        $response = $this->client->call('getLatestBlockhash', array_filter([$commitment]));

        if (! is_array($response)) {
            return [];
        }

        $value = $response['value'] ?? [];

        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<Keypair|Signer> $signers
     * @param array<string, mixed> $params
     */
    public function sendTransaction(Transaction $transaction, array $signers, array $params = []): mixed
    {
        if ($transaction->recentBlockhash === null) {
            $blockhash = $this->getLatestBlockhash()['blockhash'] ?? null;
            if (! is_string($blockhash) || $blockhash === '') {
                throw new GenericException('Unable to fetch a recent blockhash before sending the transaction.');
            }

            $transaction->recentBlockhash = $blockhash;
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
     * @param array<Keypair|Signer> $signers
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function simulateTransaction(Transaction $transaction, array $signers, array $params = []): array
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

        $response = $this->client->call('simulateTransaction', [$hashString, $send_params]);

        if (! is_array($response)) {
            return [];
        }

        /** @var array<string, mixed> $response */
        return $response;
    }

    /**
     * @param array<mixed> $params
     */
    public function requestAirdrop(array $params = []): string
    {
        $signature = $this->client->call('requestAirdrop', $params);

        return is_string($signature) ? $signature : '';
    }

    /**
     * @see https://solana.com/docs/rpc/http/getprogramaccounts
     *
     * @param array<string, mixed>|null $dataSlice Optional `{offset, length}` slice.
     * @param array<int, array<string, mixed>>|null $filters Optional RPC filter list.
     * @param array<string, mixed> $extraConfig Additional config keys forwarded verbatim.
     */
    public function getProgramAccounts(
        string  $programIdBs58,
        ?array  $dataSlice = null,
        ?array  $filters = null,
        string  $encoding = 'base64',
        array   $extraConfig = [],
    ): mixed {
        $config = ['encoding' => $encoding];

        if ($dataSlice !== null) {
            $config['dataSlice'] = $dataSlice;
        }

        if ($filters !== null) {
            $config['filters'] = $filters;
        }

        foreach ($extraConfig as $k => $v) {
            $config[$k] = $v;
        }

        return $this->client->call('getProgramAccounts', [$programIdBs58, $config]);
    }

    public function getMinimumBalanceForRentExemption(int $space = 1024): int
    {
        return (int) $this->client->call('getMinimumBalanceForRentExemption', [$space]);
    }

    public function getBlockHeight(?Commitment $commitment = null): int
    {
        $params = $commitment instanceof Commitment ? [['commitment' => (string) $commitment]] : [];

        return (int) $this->client->call('getBlockHeight', $params);
    }

    /**
     * Fetch multiple accounts in one call. Returns the raw RPC `value` array
     * (each entry an account-info dict or null).
     *
     * @param array<PublicKey|string> $publicKeys
     * @return array<array<string, mixed>|null>
     */
    public function getMultipleAccounts(array $publicKeys, string $encoding = 'base64'): array
    {
        $keys = array_map(static fn (PublicKey|string $key): string => (string) $key, $publicKeys);

        $response = $this->client->call('getMultipleAccounts', [
            $keys,
            ['encoding' => $encoding],
        ]);

        if (! is_array($response)) {
            return [];
        }

        $value = $response['value'] ?? [];

        if (! is_array($value)) {
            return [];
        }

        /** @var array<array<string, mixed>|null> $value */
        return $value;
    }

    /**
     * Look up the status of one or more transaction signatures.
     *
     * @param array<string> $signatures
     * @return array<SignatureStatus|null> Indexed identically to $signatures.
     */
    public function getSignatureStatuses(array $signatures, bool $searchTransactionHistory = false): array
    {
        $response = $this->client->call('getSignatureStatuses', [
            $signatures,
            ['searchTransactionHistory' => $searchTransactionHistory],
        ]);

        $values = is_array($response) && is_array($response['value'] ?? null)
            ? $response['value']
            : array_fill(0, count($signatures), null);

        $statuses = [];

        foreach ($signatures as $i => $signature) {
            $value = $values[$i] ?? null;
            if (! is_array($value)) {
                $statuses[] = null;

                continue;
            }

            /** @var array{slot?: int, confirmations?: int|null, confirmationStatus?: string|null, err?: mixed} $value */
            $statuses[] = SignatureStatus::fromValue($signature, $value);
        }

        return $statuses;
    }

    /**
     * Poll until the signature reaches the requested commitment level, or the
     * supplied $lastValidBlockHeight is exceeded (blockhash-expired strategy).
     * Returns the final {@see SignatureStatus}.
     *
     * @throws GenericException if the chain reports an `err` payload, the
     *         polling exceeds $timeoutSeconds, or the blockhash expires.
     */
    public function confirmTransaction(
        string      $signature,
        ?Commitment $commitment = null,
        ?int        $lastValidBlockHeight = null,
        int         $timeoutSeconds = 60,
        int         $pollIntervalMs = 500,
    ): SignatureStatus {
        $commitment ??= Commitment::confirmed();
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $statuses = $this->getSignatureStatuses([$signature], true);
            $status = $statuses[0] ?? null;

            if ($status instanceof SignatureStatus) {
                if ($status->err !== null) {
                    $err = is_string($status->err) ? $status->err : json_encode($status->err);
                    throw new GenericException("Transaction {$signature} failed on-chain: {$err}");
                }

                if ($status->reachedAtLeast($commitment)) {
                    return $status;
                }
            }

            // Compare against the live tip — `processed` advances fastest and
            // correctly trips expiry even when the caller is waiting for a
            // slower target commitment (e.g. `finalized`).
            if ($lastValidBlockHeight !== null && $this->getBlockHeight(Commitment::processed()) > $lastValidBlockHeight) {
                throw new GenericException("Blockhash expired before transaction {$signature} confirmed.");
            }

            usleep($pollIntervalMs * 1000);
        }

        throw new GenericException("Timeout waiting for transaction {$signature} confirmation after {$timeoutSeconds}s.");
    }

    // ---------------------------------------------------------------------
    // Cluster / node info
    // ---------------------------------------------------------------------

    public function getSlot(?Commitment $commitment = null): int
    {
        return (int) $this->client->call('getSlot', $this->commitmentParams($commitment));
    }

    public function getTransactionCount(?Commitment $commitment = null): int
    {
        return (int) $this->client->call('getTransactionCount', $this->commitmentParams($commitment));
    }

    public function getFirstAvailableBlock(): int
    {
        return (int) $this->client->call('getFirstAvailableBlock', []);
    }

    public function getGenesisHash(): string
    {
        $value = $this->client->call('getGenesisHash', []);

        return is_string($value) ? $value : '';
    }

    public function getHealth(): string
    {
        $value = $this->client->call('getHealth', []);

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getVersion(): array
    {
        return $this->asStringMap($this->client->call('getVersion', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function getIdentity(): array
    {
        return $this->asStringMap($this->client->call('getIdentity', []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClusterNodes(): array
    {
        $value = $this->client->call('getClusterNodes', []);

        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEpochInfo(?Commitment $commitment = null): array
    {
        return $this->asStringMap($this->client->call('getEpochInfo', $this->commitmentParams($commitment)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getEpochSchedule(): array
    {
        return $this->asStringMap($this->client->call('getEpochSchedule', []));
    }

    // ---------------------------------------------------------------------
    // Blocks / slots
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public function getBlock(int $slot, array $options = []): ?array
    {
        $value = $this->client->call('getBlock', $options === [] ? [$slot] : [$slot, $options]);

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    public function getBlockTime(int $slot): ?int
    {
        $value = $this->client->call('getBlockTime', [$slot]);

        return is_numeric($value) ? (int) $value : null;
    }

    public function isBlockhashValid(string $blockhash, ?Commitment $commitment = null): bool
    {
        $params = $commitment instanceof Commitment
            ? [$blockhash, ['commitment' => (string) $commitment]]
            : [$blockhash];

        $response = $this->client->call('isBlockhashValid', $params);

        return is_array($response) && ($response['value'] ?? false) === true;
    }

    public function getFeeForMessage(string $base64Message, ?Commitment $commitment = null): ?int
    {
        $params = $commitment instanceof Commitment
            ? [$base64Message, ['commitment' => (string) $commitment]]
            : [$base64Message];

        $response = $this->client->call('getFeeForMessage', $params);

        if (! is_array($response)) {
            return null;
        }

        $value = $response['value'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<int, string|PublicKey>|null $addresses
     * @return array<int, array{slot: int, prioritizationFee: int}>
     */
    public function getRecentPrioritizationFees(?array $addresses = null): array
    {
        $params = $addresses === null
            ? []
            : [array_map(static fn (string|PublicKey $a): string => (string) $a, $addresses)];

        $value = $this->client->call('getRecentPrioritizationFees', $params);

        /** @var array<int, array{slot: int, prioritizationFee: int}> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentPerformanceSamples(?int $limit = null): array
    {
        $value = $this->client->call('getRecentPerformanceSamples', $limit === null ? [] : [$limit]);

        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    // ---------------------------------------------------------------------
    // Signatures / history
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $options Pass `limit`, `before`, `until`,
     *        `commitment`, `minContextSlot` per the JSON-RPC spec.
     * @return array<int, array<string, mixed>>
     */
    public function getSignaturesForAddress(string|PublicKey $address, array $options = []): array
    {
        $value = $this->client->call(
            'getSignaturesForAddress',
            $options === [] ? [(string) $address] : [(string) $address, $options],
        );

        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    // ---------------------------------------------------------------------
    // Token methods
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function getTokenAccountBalance(string|PublicKey $tokenAccount, ?Commitment $commitment = null): array
    {
        $params = $commitment instanceof Commitment
            ? [(string) $tokenAccount, ['commitment' => (string) $commitment]]
            : [(string) $tokenAccount];

        return $this->asEnvelopeValue($this->client->call('getTokenAccountBalance', $params));
    }

    /**
     * @return array<string, mixed>
     */
    public function getTokenSupply(string|PublicKey $mint, ?Commitment $commitment = null): array
    {
        $params = $commitment instanceof Commitment
            ? [(string) $mint, ['commitment' => (string) $commitment]]
            : [(string) $mint];

        return $this->asEnvelopeValue($this->client->call('getTokenSupply', $params));
    }

    /**
     * Look up SPL Token accounts owned by `$owner`. `$filter` MUST be one of
     * `['mint' => <mint>]` or `['programId' => <programId>]`.
     *
     * @param array{mint?: string|PublicKey, programId?: string|PublicKey} $filter
     * @return array<int, array<string, mixed>>
     */
    public function getTokenAccountsByOwner(string|PublicKey $owner, array $filter, string $encoding = 'jsonParsed'): array
    {
        $normalized = [];
        if (isset($filter['mint'])) {
            $normalized['mint'] = (string) $filter['mint'];
        }

        if (isset($filter['programId'])) {
            $normalized['programId'] = (string) $filter['programId'];
        }

        $response = $this->client->call('getTokenAccountsByOwner', [
            (string) $owner,
            $normalized,
            ['encoding' => $encoding],
        ]);

        if (! is_array($response)) {
            return [];
        }

        $value = $response['value'] ?? [];

        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTokenLargestAccounts(string|PublicKey $mint, ?Commitment $commitment = null): array
    {
        $params = $commitment instanceof Commitment
            ? [(string) $mint, ['commitment' => (string) $commitment]]
            : [(string) $mint];

        $response = $this->client->call('getTokenLargestAccounts', $params);

        if (! is_array($response)) {
            return [];
        }

        $value = $response['value'] ?? [];

        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    // ---------------------------------------------------------------------
    // Supply / stake / vote
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function getSupply(?Commitment $commitment = null): array
    {
        return $this->asEnvelopeValue($this->client->call('getSupply', $this->commitmentParams($commitment)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getVoteAccounts(?Commitment $commitment = null): array
    {
        return $this->asStringMap($this->client->call('getVoteAccounts', $this->commitmentParams($commitment)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getInflationGovernor(?Commitment $commitment = null): array
    {
        return $this->asStringMap($this->client->call('getInflationGovernor', $this->commitmentParams($commitment)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getInflationRate(): array
    {
        return $this->asStringMap($this->client->call('getInflationRate', []));
    }

    /**
     * @param array<int, string|PublicKey> $addresses
     * @return array<int, array<string, mixed>|null>
     */
    public function getInflationReward(array $addresses, ?int $epoch = null, ?Commitment $commitment = null): array
    {
        $config = [];
        if ($epoch !== null) {
            $config['epoch'] = $epoch;
        }

        if ($commitment instanceof Commitment) {
            $config['commitment'] = (string) $commitment;
        }

        $params = [array_map(static fn (string|PublicKey $a): string => (string) $a, $addresses)];
        if ($config !== []) {
            $params[] = $config;
        }

        $value = $this->client->call('getInflationReward', $params);

        /** @var array<int, array<string, mixed>|null> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @return array<int, int>
     */
    public function getBlocks(int $startSlot, ?int $endSlot = null, ?Commitment $commitment = null): array
    {
        $params = [$startSlot];
        if ($endSlot !== null) {
            $params[] = $endSlot;
        }

        if ($commitment instanceof Commitment) {
            $params[] = ['commitment' => (string) $commitment];
        }

        $value = $this->client->call('getBlocks', $params);

        /** @var array<int, int> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @return array<int, int>
     */
    public function getBlocksWithLimit(int $startSlot, int $limit, ?Commitment $commitment = null): array
    {
        $params = [$startSlot, $limit];
        if ($commitment instanceof Commitment) {
            $params[] = ['commitment' => (string) $commitment];
        }

        $value = $this->client->call('getBlocksWithLimit', $params);

        /** @var array<int, int> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBlockCommitment(int $slot): array
    {
        return $this->asStringMap($this->client->call('getBlockCommitment', [$slot]));
    }

    /**
     * @param array<string, mixed> $options Pass `identity`, `range`, `commitment`.
     * @return array<string, mixed>
     */
    public function getBlockProduction(array $options = []): array
    {
        $response = $this->client->call('getBlockProduction', $options === [] ? [] : [$options]);

        if (! is_array($response)) {
            return [];
        }

        // RPC may inline the payload or wrap in a `{ context, value }` envelope.
        $payload = $response['value'] ?? $response;

        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * Returns the leader schedule for the given epoch (defaults to current).
     *
     * @param array<string, mixed> $options
     * @return array<string, array<int, int>>|null
     */
    public function getLeaderSchedule(?int $slot = null, array $options = []): ?array
    {
        $params = [];
        if ($slot !== null) {
            $params[] = $slot;
        }

        if ($options !== []) {
            if ($slot === null) {
                $params[] = null;
            }

            $params[] = $options;
        }

        $value = $this->client->call('getLeaderSchedule', $params);

        /** @var array<string, array<int, int>>|null $payload */
        $payload = is_array($value) ? $value : null;

        return $payload;
    }

    /**
     * @param array<string, mixed> $options `filter` ('circulating'|'nonCirculating'), `commitment`.
     * @return array<int, array<string, mixed>>
     */
    public function getLargestAccounts(array $options = []): array
    {
        $response = $this->client->call('getLargestAccounts', $options === [] ? [] : [$options]);

        if (! is_array($response)) {
            return [];
        }

        $value = $response['value'] ?? [];

        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function getStakeActivation(string|PublicKey $stakeAccount, array $options = []): array
    {
        return $this->asStringMap($this->client->call(
            'getStakeActivation',
            $options === [] ? [(string) $stakeAccount] : [(string) $stakeAccount, $options],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getHighestSnapshotSlot(): array
    {
        return $this->asStringMap($this->client->call('getHighestSnapshotSlot', []));
    }

    public function minimumLedgerSlot(): int
    {
        return (int) $this->client->call('minimumLedgerSlot', []);
    }

    public function getMaxRetransmitSlot(): int
    {
        return (int) $this->client->call('getMaxRetransmitSlot', []);
    }

    public function getMaxShredInsertSlot(): int
    {
        return (int) $this->client->call('getMaxShredInsertSlot', []);
    }

    public function getSlotLeader(?Commitment $commitment = null): string
    {
        $value = $this->client->call('getSlotLeader', $this->commitmentParams($commitment));

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<int, string>
     */
    public function getSlotLeaders(int $startSlot, int $limit): array
    {
        $value = $this->client->call('getSlotLeaders', [$startSlot, $limit]);

        /** @var array<int, string> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    /**
     * @param array{mint?: string|PublicKey, programId?: string|PublicKey} $filter
     * @return array<int, array<string, mixed>>
     */
    public function getTokenAccountsByDelegate(string|PublicKey $delegate, array $filter, string $encoding = 'jsonParsed'): array
    {
        $normalized = [];
        if (isset($filter['mint'])) {
            $normalized['mint'] = (string) $filter['mint'];
        }

        if (isset($filter['programId'])) {
            $normalized['programId'] = (string) $filter['programId'];
        }

        $response = $this->client->call('getTokenAccountsByDelegate', [
            (string) $delegate,
            $normalized,
            ['encoding' => $encoding],
        ]);

        if (! is_array($response)) {
            return [];
        }

        $value = $response['value'] ?? [];

        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($value) ? $value : [];

        return $list;
    }

    public function getStakeMinimumDelegation(?Commitment $commitment = null): int
    {
        $response = $this->client->call('getStakeMinimumDelegation', $this->commitmentParams($commitment));

        if (! is_array($response)) {
            return 0;
        }

        $value = $response['value'] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    // ---------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function commitmentParams(?Commitment $commitment): array
    {
        return $commitment instanceof Commitment ? [['commitment' => (string) $commitment]] : [];
    }

    /**
     * Narrow a `mixed` RPC payload to `array<string, mixed>`, returning `[]`
     * when the response is not an array. Used as the boundary cast between
     * `SolanaRpcClient::call()` (`mixed`) and typed `Connection` returns.
     *
     * @return array<string, mixed>
     */
    private function asStringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Same as {@see self::asStringMap()} but for the `value` field of the
     * standard `{ context, value }` RPC envelope.
     *
     * @return array<string, mixed>
     */
    private function asEnvelopeValue(mixed $response): array
    {
        if (! is_array($response)) {
            return [];
        }

        $value = $response['value'] ?? null;

        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Submit a {@see VersionedTransaction} that has been signed by the caller.
     * Returns the signature string.
     *
     * @param array<string, mixed> $params
     */
    public function sendRawTransaction(string $rawTransaction, array $params = []): string
    {
        $hashString = sodium_bin2base64($rawTransaction, SODIUM_BASE64_VARIANT_ORIGINAL);

        $send_params = ['encoding' => 'base64', 'preflightCommitment' => 'confirmed'];

        foreach ($params as $k => $v) {
            $send_params[$k] = $v;
        }

        $signature = $this->client->call('sendTransaction', [$hashString, $send_params]);

        return is_string($signature) ? $signature : '';
    }

    /**
     * Send a fully-signed versioned transaction without re-signing or mutating it.
     *
     * @param array<string, mixed> $params
     */
    public function sendVersionedTransaction(VersionedTransaction $transaction, array $params = []): string
    {
        return $this->sendRawTransaction($transaction->serialize(), $params);
    }
}
