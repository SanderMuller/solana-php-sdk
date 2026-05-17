<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use Generator;
use SanderMuller\SolanaPhpSdk\DataObjects\AccountInfo;
use SanderMuller\SolanaPhpSdk\DataObjects\BlockhashInfo;
use SanderMuller\SolanaPhpSdk\DataObjects\ProgramAccount;
use SanderMuller\SolanaPhpSdk\DataObjects\SignatureStatus;
use SanderMuller\SolanaPhpSdk\DataObjects\TransactionStatement;
use SanderMuller\SolanaPhpSdk\Enum\Encoding;
use SanderMuller\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use SanderMuller\SolanaPhpSdk\Exceptions\BlockhashExpiredException;
use SanderMuller\SolanaPhpSdk\Exceptions\ConfirmationTimeoutException;
use SanderMuller\SolanaPhpSdk\Exceptions\GenericException;
use SanderMuller\SolanaPhpSdk\Exceptions\RpcException;
use SanderMuller\SolanaPhpSdk\Exceptions\SendTransactionError;
use SanderMuller\SolanaPhpSdk\Exceptions\TransactionFailedOnChainException;
use SanderMuller\SolanaPhpSdk\Programs\IsProgram;
use SanderMuller\SolanaPhpSdk\Programs\Program;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\DecodedTransaction;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\IdlRegistry;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\TransactionDecoder;
use SanderMuller\SolanaPhpSdk\Util\Commitment;
use SanderMuller\SolanaPhpSdk\Util\Signer;

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

    /**
     * Typed companion to {@see getAccountInfo()}. Returns an {@see AccountInfo}
     * DTO with the data field decoded to a {@see Buffer}
     * so callers do not have to remember the `[base64-payload, "base64"]`
     * tuple shape.
     *
     * @throws AccountNotFoundException when the account does not exist.
     */
    public function accountInfo(string|PublicKey $walletAddress, Encoding|string $encoding = Encoding::BASE64): AccountInfo
    {
        $response = $this->client->call('getAccountInfo', [
            (string) $walletAddress,
            ['encoding' => $this->encodingValue($encoding)],
        ]);

        $value = is_array($response) ? ($response['value'] ?? null) : null;

        if (! is_array($value)) {
            throw new AccountNotFoundException("API Error: Account {$walletAddress} not found.");
        }

        /** @var array<string, mixed> $value */
        return AccountInfo::fromValue($value);
    }

    /**
     * Typed companion to {@see getMultipleAccounts()}. Each entry is either
     * an {@see AccountInfo} DTO or `null` when the slot reports no account
     * at that address.
     *
     * @param array<PublicKey|string> $publicKeys
     * @return array<int, AccountInfo|null>
     */
    public function multipleAccounts(array $publicKeys, Encoding|string $encoding = Encoding::BASE64): array
    {
        $rows = $this->getMultipleAccounts($publicKeys, $encoding);

        return array_values(array_map(
            static fn (?array $row): ?AccountInfo => $row === null ? null : AccountInfo::fromValue($row),
            $rows,
        ));
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
     * Fetch a transaction by signature and return the fully-decoded
     * tree (per-instruction program id + role-tagged accounts + data
     * + inner-instruction CPIs + parsed logs). Pass an
     * {@see IdlRegistry} to attach IDL-resolved instruction names +
     * arg maps (Phase 3 of the spec).
     *
     * Calls the RPC with `encoding: 'json'` +
     * `maxSupportedTransactionVersion: 0` so legacy and V0
     * transactions decode through the same path. Returns null when
     * the signature has no on-chain record.
     */
    public function decodeTransaction(
        string $transactionSignature,
        ?IdlRegistry $registry = null,
        ?Commitment $commitment = null,
    ): ?DecodedTransaction {
        $params = [
            'encoding' => 'json',
            'maxSupportedTransactionVersion' => 0,
        ];
        if ($commitment instanceof Commitment) {
            $params['commitment'] = $commitment->commitmentLevel;
        }

        $response = $this->client->call('getTransaction', [$transactionSignature, $params]);

        if (! is_array($response)) {
            return null;
        }

        /** @var array<string, mixed> $response */
        return TransactionDecoder::fromRpcResponse($response, $registry ?? new IdlRegistry());
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
     * Typed companion to {@see getLatestBlockhash()}. Returns a
     * {@see BlockhashInfo} DTO so callers do not have to remember the array
     * shape.
     */
    public function latestBlockhash(?Commitment $commitment = null): BlockhashInfo
    {
        return BlockhashInfo::fromValue($this->getLatestBlockhash($commitment));
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

        // Pass every signer in a single `sign(...)` call. `Transaction::sign`
        // rebuilds the signature vector from the provided list per call, so
        // looping `sign($signer)` per signer would overwrite earlier slots and
        // emit a 1-of-N transaction for any multisig flow.
        if ($signers !== []) {
            $transaction->sign(...$signers);
        }

        $rawBinaryString = $transaction->serialize(false);

        $hashString = sodium_bin2base64($rawBinaryString, SODIUM_BASE64_VARIANT_ORIGINAL);

        $send_params = ['encoding' => 'base64', 'preflightCommitment' => 'confirmed'];

        foreach ($params as $k => $v) {
            $send_params[$k] = $v;
        }

        try {
            return $this->client->call('sendTransaction', [$hashString, $send_params]);
        } catch (RpcException $rpcException) {
            throw SendTransactionError::tryFromRpc($rpcException) ?? $rpcException;
        }
    }

    /**
     * @param array<Keypair|Signer> $signers
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function simulateTransaction(Transaction $transaction, array $signers, array $params = []): array
    {
        if ($signers !== []) {
            $transaction->sign(...$signers);
        }

        $rawBinaryString = $transaction->serialize(false);

        $hashString = sodium_bin2base64($rawBinaryString, SODIUM_BASE64_VARIANT_ORIGINAL);

        $send_params = ['encoding' => 'base64', 'commitment' => 'confirmed', 'sigVerify' => true];

        foreach ($params as $k => $v) {
            $send_params[$k] = $v;
        }

        try {
            $response = $this->client->call('simulateTransaction', [$hashString, $send_params]);
        } catch (RpcException $rpcException) {
            throw SendTransactionError::tryFromRpc($rpcException) ?? $rpcException;
        }

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
        string             $programIdBs58,
        ?array             $dataSlice = null,
        ?array             $filters = null,
        Encoding|string    $encoding = Encoding::BASE64,
        array              $extraConfig = [],
    ): mixed {
        $config = ['encoding' => $this->encodingValue($encoding)];

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

    /**
     * Typed companion to {@see getProgramAccounts()}. Returns a list of
     * {@see ProgramAccount} DTOs (pubkey + decoded {@see AccountInfo})
     * instead of the raw `mixed` shape. Server-side filtering is
     * unchanged — pass `GpaFilter::memcmp(...)` / `GpaFilter::dataSize(...)`
     * for type-safe filter construction.
     *
     * @param array<int, array<string, mixed>>|null $filters
     * @param array<string, mixed> $extraConfig
     * @param array<string, mixed>|null $dataSlice
     *
     * @return array<int, ProgramAccount>
     */
    public function programAccounts(
        string|PublicKey $programId,
        ?array $dataSlice = null,
        ?array $filters = null,
        Encoding|string $encoding = Encoding::BASE64,
        array $extraConfig = [],
    ): array {
        $raw = $this->getProgramAccounts((string) $programId, $dataSlice, $filters, $encoding, $extraConfig);

        if (! is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = ProgramAccount::fromRow($row);
            }
        }

        return $rows;
    }

    /**
     * Paginate a large `getProgramAccounts` scan by partitioning on
     * `dataSize`. Solana RPC exposes no native cursor — most providers
     * cap the result at 10–100k rows and time out beyond that. The
     * recipe most production indexers use is "scan one dataSize bucket
     * per round-trip"; this helper formalises that pattern.
     *
     * `$dataSizes` is the list of layout-specific account sizes to walk
     * (e.g. `[165]` for SPL Token accounts, `[82, 165]` for mints +
     * accounts). Additional shared filters are appended to each bucket.
     *
     * @param array<int> $dataSizes
     * @param array<int, array<string, mixed>> $extraFilters
     *
     * @return Generator<int, ProgramAccount>
     */
    public function programAccountsPaged(
        string|PublicKey $programId,
        array $dataSizes,
        array $extraFilters = [],
        Encoding|string $encoding = Encoding::BASE64,
    ): Generator {
        foreach ($dataSizes as $size) {
            $filters = [['dataSize' => $size], ...$extraFilters];

            foreach ($this->programAccounts($programId, null, $filters, $encoding) as $row) {
                yield $row;
            }
        }
    }

    public function getMinimumBalanceForRentExemption(int $space = 1024): int
    {
        return $this->asInt($this->client->call('getMinimumBalanceForRentExemption', [$space]));
    }

    public function getBlockHeight(?Commitment $commitment = null): int
    {
        $params = $commitment instanceof Commitment ? [['commitment' => (string) $commitment]] : [];

        return $this->asInt($this->client->call('getBlockHeight', $params));
    }

    /**
     * Fetch multiple accounts in one call. Returns the raw RPC `value` array
     * (each entry an account-info dict or null).
     *
     * @param array<PublicKey|string> $publicKeys
     * @return array<array<string, mixed>|null>
     */
    public function getMultipleAccounts(array $publicKeys, Encoding|string $encoding = Encoding::BASE64): array
    {
        $keys = array_map(static fn (PublicKey|string $key): string => (string) $key, $publicKeys);

        $response = $this->client->call('getMultipleAccounts', [
            $keys,
            ['encoding' => $this->encodingValue($encoding)],
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
                    throw new TransactionFailedOnChainException(
                        $signature,
                        $status->err,
                        "Transaction {$signature} failed on-chain: {$err}",
                    );
                }

                if ($status->reachedAtLeast($commitment)) {
                    return $status;
                }
            }

            // Compare against the live tip — `processed` advances fastest and
            // correctly trips expiry even when the caller is waiting for a
            // slower target commitment (e.g. `finalized`).
            if ($lastValidBlockHeight !== null && $this->getBlockHeight(Commitment::processed()) > $lastValidBlockHeight) {
                throw new BlockhashExpiredException(
                    $signature,
                    "Blockhash expired before transaction {$signature} confirmed.",
                );
            }

            usleep($pollIntervalMs * 1000);
        }

        throw new ConfirmationTimeoutException(
            $signature,
            $timeoutSeconds,
            "Timeout waiting for transaction {$signature} confirmation after {$timeoutSeconds}s.",
        );
    }

    // ---------------------------------------------------------------------
    // Cluster / node info
    // ---------------------------------------------------------------------

    public function getSlot(?Commitment $commitment = null): int
    {
        return $this->asInt($this->client->call('getSlot', $this->commitmentParams($commitment)));
    }

    public function getTransactionCount(?Commitment $commitment = null): int
    {
        return $this->asInt($this->client->call('getTransactionCount', $this->commitmentParams($commitment)));
    }

    public function getFirstAvailableBlock(): int
    {
        return $this->asInt($this->client->call('getFirstAvailableBlock', []));
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
    public function getTokenAccountsByOwner(string|PublicKey $owner, array $filter, Encoding|string $encoding = Encoding::JSON_PARSED): array
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
            ['encoding' => $this->encodingValue($encoding)],
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
        return $this->asInt($this->client->call('minimumLedgerSlot', []));
    }

    public function getMaxRetransmitSlot(): int
    {
        return $this->asInt($this->client->call('getMaxRetransmitSlot', []));
    }

    public function getMaxShredInsertSlot(): int
    {
        return $this->asInt($this->client->call('getMaxShredInsertSlot', []));
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
    public function getTokenAccountsByDelegate(string|PublicKey $delegate, array $filter, Encoding|string $encoding = Encoding::JSON_PARSED): array
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
            ['encoding' => $this->encodingValue($encoding)],
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
     * Narrow a `mixed` RPC payload to `int`, returning `0` when the response
     * is not numeric. Used by `getSlot`/`getBlockHeight`/etc to satisfy strict
     * casting from the `SolanaRpcClient::call()` `mixed` return.
     */
    private function asInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
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

        try {
            $signature = $this->client->call('sendTransaction', [$hashString, $send_params]);
        } catch (RpcException $rpcException) {
            throw SendTransactionError::tryFromRpc($rpcException) ?? $rpcException;
        }

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

    /**
     * Send a legacy transaction and block until it reaches the requested
     * commitment, using the blockhash-expiry strategy. Pairs `sendTransaction`
     * with `confirmTransaction`; the blockhash and its expiry height are
     * fetched together (single RPC) so the two phases agree.
     *
     * @param array<Keypair|Signer> $signers
     * @param array<string, mixed> $params Forwarded to `sendTransaction`.
     */
    public function sendAndConfirmTransaction(
        Transaction $transaction,
        array $signers,
        array $params = [],
        ?Commitment $commitment = null,
        int $timeoutSeconds = 60,
        int $pollIntervalMs = 500,
    ): SignatureStatus {
        $blockhashInfo = $this->getLatestBlockhash();
        $blockhash = $blockhashInfo['blockhash'] ?? null;
        $lastValid = $blockhashInfo['lastValidBlockHeight'] ?? null;

        if (! is_string($blockhash) || $blockhash === '') {
            throw new GenericException('Unable to fetch a recent blockhash before sending the transaction.');
        }

        $transaction->recentBlockhash = $blockhash;

        $signature = $this->sendTransaction($transaction, $signers, $params);

        if (! is_string($signature) || $signature === '') {
            throw new GenericException('sendTransaction did not return a signature.');
        }

        return $this->confirmTransaction(
            $signature,
            $commitment,
            is_int($lastValid) ? $lastValid : null,
            $timeoutSeconds,
            $pollIntervalMs,
        );
    }

    private function encodingValue(Encoding|string $encoding): string
    {
        return $encoding instanceof Encoding ? $encoding->value : $encoding;
    }

    /**
     * Versioned-transaction counterpart of {@see sendAndConfirmTransaction}.
     * The caller is responsible for signing and (where applicable) selecting
     * the lifetime blockhash; passing `$lastValidBlockHeight` enables the
     * blockhash-expiry guard.
     *
     * @param array<string, mixed> $params
     */
    public function sendAndConfirmVersionedTransaction(
        VersionedTransaction $transaction,
        array $params = [],
        ?int $lastValidBlockHeight = null,
        ?Commitment $commitment = null,
        int $timeoutSeconds = 60,
        int $pollIntervalMs = 500,
    ): SignatureStatus {
        $signature = $this->sendVersionedTransaction($transaction, $params);

        if ($signature === '') {
            throw new GenericException('sendTransaction did not return a signature.');
        }

        return $this->confirmTransaction(
            $signature,
            $commitment,
            $lastValidBlockHeight,
            $timeoutSeconds,
            $pollIntervalMs,
        );
    }
}
