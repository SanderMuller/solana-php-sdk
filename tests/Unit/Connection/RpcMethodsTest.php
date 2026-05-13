<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Util\Commitment;

/**
 * Smoke-tests for each typed JSON-RPC wrapper on {@see Connection}. Mocks the
 * HTTP layer via {@see TestCase::fakeRpcByMethod()} and asserts that the
 * helper coerces the JSON-RPC response shape into the expected return type.
 */
final class RpcMethodsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->container->get(Connection::class);
    }

    #[Test]
    public function get_slot_returns_int(): void
    {
        $this->fakeRpcByMethod(['getSlot' => 12345]);

        self::assertSame(12345, $this->connection->getSlot());
        self::assertSame(12345, $this->connection->getSlot(Commitment::finalized()));
    }

    #[Test]
    public function get_transaction_count(): void
    {
        $this->fakeRpcByMethod(['getTransactionCount' => 987654321]);

        self::assertSame(987654321, $this->connection->getTransactionCount());
    }

    #[Test]
    public function get_first_available_block(): void
    {
        $this->fakeRpcByMethod(['getFirstAvailableBlock' => 250_000_000]);

        self::assertSame(250_000_000, $this->connection->getFirstAvailableBlock());
    }

    #[Test]
    public function get_genesis_hash(): void
    {
        $this->fakeRpcByMethod(['getGenesisHash' => '5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d']);

        self::assertSame('5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d', $this->connection->getGenesisHash());
    }

    #[Test]
    public function get_health_returns_string(): void
    {
        $this->fakeRpcByMethod(['getHealth' => 'ok']);

        self::assertSame('ok', $this->connection->getHealth());
    }

    #[Test]
    public function get_version(): void
    {
        $payload = ['solana-core' => '2.1.0', 'feature-set' => 1234567890];
        $this->fakeRpcByMethod(['getVersion' => $payload]);

        self::assertSame($payload, $this->connection->getVersion());
    }

    #[Test]
    public function get_identity(): void
    {
        $payload = ['identity' => '2r1F4iWqVcb8M1DbAjQuFpebkQHY9hcVU4WuW1DRu5iT'];
        $this->fakeRpcByMethod(['getIdentity' => $payload]);

        self::assertSame($payload, $this->connection->getIdentity());
    }

    #[Test]
    public function get_cluster_nodes(): void
    {
        $payload = [
            ['pubkey' => 'abc', 'gossip' => '1.2.3.4:8001', 'tpu' => '1.2.3.4:8003'],
            ['pubkey' => 'def', 'gossip' => '5.6.7.8:8001', 'tpu' => '5.6.7.8:8003'],
        ];
        $this->fakeRpcByMethod(['getClusterNodes' => $payload]);

        self::assertSame($payload, $this->connection->getClusterNodes());
    }

    #[Test]
    public function get_epoch_info(): void
    {
        $payload = ['epoch' => 700, 'slotIndex' => 1000, 'slotsInEpoch' => 432_000, 'absoluteSlot' => 302_401_000];
        $this->fakeRpcByMethod(['getEpochInfo' => $payload]);

        self::assertSame($payload, $this->connection->getEpochInfo(Commitment::confirmed()));
    }

    #[Test]
    public function get_epoch_schedule(): void
    {
        $payload = ['slotsPerEpoch' => 432_000, 'leaderScheduleSlotOffset' => 432_000, 'warmup' => false];
        $this->fakeRpcByMethod(['getEpochSchedule' => $payload]);

        self::assertSame($payload, $this->connection->getEpochSchedule());
    }

    #[Test]
    public function get_block_returns_null_when_missing(): void
    {
        $this->fakeRpcByMethod(['getBlock' => null]);

        self::assertNull($this->connection->getBlock(123));
    }

    #[Test]
    public function get_block_returns_payload(): void
    {
        $payload = ['blockHeight' => 123, 'blockTime' => 1_700_000_000, 'parentSlot' => 122, 'transactions' => []];
        $this->fakeRpcByMethod(['getBlock' => $payload]);

        self::assertSame($payload, $this->connection->getBlock(123, ['transactionDetails' => 'none']));
    }

    #[Test]
    public function get_block_time(): void
    {
        $this->fakeRpcByMethod(['getBlockTime' => 1_700_000_000]);

        self::assertSame(1_700_000_000, $this->connection->getBlockTime(123));
    }

    #[Test]
    public function is_blockhash_valid(): void
    {
        $this->fakeRpcByMethod(['isBlockhashValid' => ['value' => true]]);

        self::assertTrue($this->connection->isBlockhashValid('EETubP5AKHgjPAhzPAFcb8BAY1hMH639CWCFTqi3hq1k'));
    }

    #[Test]
    public function is_blockhash_valid_returns_false_for_invalid(): void
    {
        $this->fakeRpcByMethod(['isBlockhashValid' => ['value' => false]]);

        self::assertFalse($this->connection->isBlockhashValid('EETubP5AKHgjPAhzPAFcb8BAY1hMH639CWCFTqi3hq1k'));
    }

    #[Test]
    public function get_fee_for_message(): void
    {
        $this->fakeRpcByMethod(['getFeeForMessage' => ['value' => 5000]]);

        self::assertSame(5000, $this->connection->getFeeForMessage('base64messagehere'));
    }

    #[Test]
    public function get_fee_for_message_returns_null_when_blockhash_expired(): void
    {
        $this->fakeRpcByMethod(['getFeeForMessage' => ['value' => null]]);

        self::assertNull($this->connection->getFeeForMessage('base64messagehere'));
    }

    #[Test]
    public function get_recent_prioritization_fees(): void
    {
        $payload = [
            ['slot' => 300_000_000, 'prioritizationFee' => 12_345],
            ['slot' => 300_000_001, 'prioritizationFee' => 6_789],
        ];
        $this->fakeRpcByMethod(['getRecentPrioritizationFees' => $payload]);

        self::assertSame($payload, $this->connection->getRecentPrioritizationFees());
    }

    #[Test]
    public function get_recent_performance_samples(): void
    {
        $payload = [
            ['slot' => 1, 'numTransactions' => 100, 'numSlots' => 10, 'samplePeriodSecs' => 60],
        ];
        $this->fakeRpcByMethod(['getRecentPerformanceSamples' => $payload]);

        self::assertSame($payload, $this->connection->getRecentPerformanceSamples(5));
    }

    #[Test]
    public function get_signatures_for_address(): void
    {
        $payload = [
            ['signature' => 'sig1', 'slot' => 1, 'blockTime' => 1700, 'err' => null],
            ['signature' => 'sig2', 'slot' => 2, 'blockTime' => 1701, 'err' => null],
        ];
        $this->fakeRpcByMethod(['getSignaturesForAddress' => $payload]);

        self::assertSame(
            $payload,
            $this->connection->getSignaturesForAddress('3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX', ['limit' => 10]),
        );
    }

    #[Test]
    public function get_token_account_balance(): void
    {
        $value = ['amount' => '1000', 'decimals' => 6, 'uiAmount' => 0.001, 'uiAmountString' => '0.001'];
        $this->fakeRpcByMethod(['getTokenAccountBalance' => ['value' => $value]]);

        self::assertSame($value, $this->connection->getTokenAccountBalance('TokenAcc11111111111111111111111111111111111'));
    }

    #[Test]
    public function get_token_supply(): void
    {
        $value = ['amount' => '1000000000', 'decimals' => 6, 'uiAmount' => 1000.25, 'uiAmountString' => '1000.25'];
        $this->fakeRpcByMethod(['getTokenSupply' => ['value' => $value]]);

        self::assertSame($value, $this->connection->getTokenSupply('MintAddr1111111111111111111111111111111111'));
    }

    #[Test]
    public function get_token_accounts_by_owner_with_mint_filter(): void
    {
        $payload = [
            ['pubkey' => 'acc1', 'account' => ['lamports' => 2_000_000]],
        ];
        $this->fakeRpcByMethod(['getTokenAccountsByOwner' => ['value' => $payload]]);

        $result = $this->connection->getTokenAccountsByOwner(
            'OwnerAddr1111111111111111111111111111111111',
            ['mint' => 'MintAddr1111111111111111111111111111111111'],
        );

        self::assertSame($payload, $result);
    }

    #[Test]
    public function get_token_largest_accounts(): void
    {
        $payload = [['address' => 'acc1', 'amount' => '500', 'decimals' => 6, 'uiAmount' => 0.0005, 'uiAmountString' => '0.0005']];
        $this->fakeRpcByMethod(['getTokenLargestAccounts' => ['value' => $payload]]);

        self::assertSame($payload, $this->connection->getTokenLargestAccounts('MintAddr1111111111111111111111111111111111'));
    }

    #[Test]
    public function get_supply(): void
    {
        $value = ['total' => 588_000_000_000, 'circulating' => 480_000_000_000, 'nonCirculating' => 108_000_000_000];
        $this->fakeRpcByMethod(['getSupply' => ['value' => $value]]);

        self::assertSame($value, $this->connection->getSupply());
    }

    #[Test]
    public function get_vote_accounts(): void
    {
        $payload = ['current' => [], 'delinquent' => []];
        $this->fakeRpcByMethod(['getVoteAccounts' => $payload]);

        self::assertSame($payload, $this->connection->getVoteAccounts());
    }

    #[Test]
    public function get_inflation_governor(): void
    {
        $payload = ['initial' => 0.08, 'terminal' => 0.015, 'taper' => 0.15, 'foundation' => 0.05, 'foundationTerm' => 7.5];
        $this->fakeRpcByMethod(['getInflationGovernor' => $payload]);

        self::assertSame($payload, $this->connection->getInflationGovernor());
    }

    #[Test]
    public function get_inflation_rate(): void
    {
        $payload = ['total' => 0.05, 'validator' => 0.045, 'foundation' => 0.005, 'epoch' => 700];
        $this->fakeRpcByMethod(['getInflationRate' => $payload]);

        self::assertSame($payload, $this->connection->getInflationRate());
    }

    #[Test]
    public function get_inflation_reward(): void
    {
        $payload = [
            ['epoch' => 699, 'effectiveSlot' => 302_000_000, 'amount' => 12_345, 'postBalance' => 100_000_000, 'commission' => 5],
            null,
        ];
        $this->fakeRpcByMethod(['getInflationReward' => $payload]);

        $result = $this->connection->getInflationReward(
            ['Addr111111111111111111111111111111111111111', 'Addr222222222222222222222222222222222222222'],
            epoch: 699,
        );

        self::assertSame($payload, $result);
    }

    #[Test]
    public function get_stake_minimum_delegation(): void
    {
        $this->fakeRpcByMethod(['getStakeMinimumDelegation' => ['value' => 1_000_000_000]]);

        self::assertSame(1_000_000_000, $this->connection->getStakeMinimumDelegation());
    }

    #[Test]
    public function get_blocks(): void
    {
        $this->fakeRpcByMethod(['getBlocks' => [1, 2, 3, 4, 5]]);

        self::assertSame([1, 2, 3, 4, 5], $this->connection->getBlocks(1, 5));
    }

    #[Test]
    public function get_blocks_with_limit(): void
    {
        $this->fakeRpcByMethod(['getBlocksWithLimit' => [100, 101, 102]]);

        self::assertSame([100, 101, 102], $this->connection->getBlocksWithLimit(100, 3));
    }

    #[Test]
    public function get_block_commitment(): void
    {
        $payload = ['commitment' => [1, 2, 3], 'totalStake' => 1_000_000];
        $this->fakeRpcByMethod(['getBlockCommitment' => $payload]);

        self::assertSame($payload, $this->connection->getBlockCommitment(123));
    }

    #[Test]
    public function get_block_production(): void
    {
        $payload = ['byIdentity' => ['abc' => [10, 8]], 'range' => ['firstSlot' => 1, 'lastSlot' => 100]];
        $this->fakeRpcByMethod(['getBlockProduction' => ['value' => $payload]]);

        self::assertSame($payload, $this->connection->getBlockProduction());
    }

    #[Test]
    public function get_leader_schedule(): void
    {
        $payload = ['ValidatorPubkey1' => [0, 1, 2, 3], 'ValidatorPubkey2' => [4, 5, 6, 7]];
        $this->fakeRpcByMethod(['getLeaderSchedule' => $payload]);

        self::assertSame($payload, $this->connection->getLeaderSchedule());
    }

    #[Test]
    public function get_largest_accounts(): void
    {
        $payload = [
            ['address' => 'addr1', 'lamports' => 999_999_999],
            ['address' => 'addr2', 'lamports' => 888_888_888],
        ];
        $this->fakeRpcByMethod(['getLargestAccounts' => ['value' => $payload]]);

        self::assertSame($payload, $this->connection->getLargestAccounts(['filter' => 'circulating']));
    }

    #[Test]
    public function get_stake_activation(): void
    {
        $payload = ['state' => 'active', 'active' => 1_000_000_000, 'inactive' => 0];
        $this->fakeRpcByMethod(['getStakeActivation' => $payload]);

        self::assertSame($payload, $this->connection->getStakeActivation('StakeAcc11111111111111111111111111111111111'));
    }

    #[Test]
    public function get_highest_snapshot_slot(): void
    {
        $payload = ['full' => 300_000_000, 'incremental' => 300_000_500];
        $this->fakeRpcByMethod(['getHighestSnapshotSlot' => $payload]);

        self::assertSame($payload, $this->connection->getHighestSnapshotSlot());
    }

    #[Test]
    public function minimum_ledger_slot(): void
    {
        $this->fakeRpcByMethod(['minimumLedgerSlot' => 250_000_000]);

        self::assertSame(250_000_000, $this->connection->minimumLedgerSlot());
    }

    #[Test]
    public function get_max_retransmit_slot(): void
    {
        $this->fakeRpcByMethod(['getMaxRetransmitSlot' => 300_500_000]);

        self::assertSame(300_500_000, $this->connection->getMaxRetransmitSlot());
    }

    #[Test]
    public function get_max_shred_insert_slot(): void
    {
        $this->fakeRpcByMethod(['getMaxShredInsertSlot' => 300_500_010]);

        self::assertSame(300_500_010, $this->connection->getMaxShredInsertSlot());
    }

    #[Test]
    public function get_slot_leader(): void
    {
        $this->fakeRpcByMethod(['getSlotLeader' => 'LeaderPubkey1111111111111111111111111111111']);

        self::assertSame('LeaderPubkey1111111111111111111111111111111', $this->connection->getSlotLeader());
    }

    #[Test]
    public function get_slot_leaders(): void
    {
        $payload = ['L1', 'L2', 'L3', 'L4'];
        $this->fakeRpcByMethod(['getSlotLeaders' => $payload]);

        self::assertSame($payload, $this->connection->getSlotLeaders(100, 4));
    }

    #[Test]
    public function get_token_accounts_by_delegate(): void
    {
        $payload = [['pubkey' => 'acc1', 'account' => ['lamports' => 1000]]];
        $this->fakeRpcByMethod(['getTokenAccountsByDelegate' => ['value' => $payload]]);

        $result = $this->connection->getTokenAccountsByDelegate(
            'DelegatePubkey111111111111111111111111111111',
            ['mint' => 'MintAddr1111111111111111111111111111111111'],
        );

        self::assertSame($payload, $result);
    }
}
