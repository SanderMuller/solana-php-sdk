# solana-php-sdk

A PHP SDK for the [Solana](https://solana.com) blockchain. Read accounts,
sign and submit transactions, watch the chain over WebSockets, and call any
Anchor program — all from PHP.

> **Status:** open source under [MIT](LICENSE). Works on PHP 8.3 / 8.4 with
> Laravel 11 / 12 / 13.

---

## What you get

| Layer | What it does | Class |
|---|---|---|
| Keys & signatures | Generate keypairs, sign messages, verify Ed25519 signatures | `Keypair`, `PublicKey` |
| Raw JSON-RPC | One method: `call($method, $params)` — pass anything Solana defines | `Services\SolanaRpcClient` |
| Typed JSON-RPC | ~50 typed methods covering ~90 % of the HTTP-RPC spec | `Connection` |
| WebSocket PubSub | Account / signature / slot / logs / program / vote / block subscriptions | `Services\SolanaPubSubClient` |
| Transactions | Legacy + V0 (versioned) transactions, Address Lookup Tables | `Transaction`, `VersionedTransaction`, `MessageV0` |
| Programs | SPL Token, System, ComputeBudget, Metaplex, DID-Sol, SNS (name service) | `Programs\*` |
| Anchor | Parse any Anchor IDL at runtime → typed `TransactionInstruction` builders | `Anchor\AnchorIdl` |

## Who this is for

- **PHP devs new to Solana.** Start with the [5-minute quickstart](#5-minute-quickstart) — you'll send a real on-chain transfer.
- **Laravel apps.** The package auto-registers a service provider; resolve `Connection` straight from the container.
- **Standalone PHP scripts.** A one-liner bootstrap (`Bootstrap::createContainer`) wires up the same container outside Laravel.

---

## Install

```bash
composer require collectiq/solana-php-sdk
```

The package self-registers `Collectiq\SolanaPhpSdk\ServiceProvider` via
Laravel's [package discovery](https://laravel.com/docs/packages#package-discovery).
Standalone PHP doesn't need anything extra — see
["Outside Laravel"](#outside-laravel) below.

Requirements: PHP 8.3+ with `ext-sodium` enabled (every mainstream PHP distro
ships it). Composer pulls in `paragonie/sodium_compat` as a polyfill backup.

---

## 5-minute quickstart

We'll go from zero to a signed-and-broadcast SOL transfer on devnet.

### 1. Generate (or load) a keypair

```php
use Collectiq\SolanaPhpSdk\Keypair;

$payer = Keypair::generate();
echo $payer->getPublicKey()->toBase58(), "\n";
// → e.g.  4uTYf8w...EPnL  (this is your wallet address)
```

Store the secret bytes somewhere safe if you want to reuse this wallet:

```php
$secret = $payer->getSecretKey()->toArray(); // array<int, int> — 64 bytes
// Reload with:  Keypair::fromSecretKey($secret);
```

### 2. Get some devnet SOL

```php
use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Enum\Network;

$connection = app(Connection::class);          // Laravel
// $connection = Bootstrap::createContainer(__DIR__.'/config.php')->get(Connection::class); // standalone

$signature = $connection->requestAirdrop([
    $payer->getPublicKey()->toBase58(),
    1_000_000_000,                              // 1 SOL in lamports
]);
$connection->confirmTransaction($signature);    // blocks until landed
```

### 3. Check your balance

```php
$lamports = $connection->getBalance($payer->getPublicKey()->toBase58());
echo $lamports / 1_000_000_000, " SOL\n";       // → 1.0
```

### 4. Send SOL to someone

```php
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\Transaction;

$recipient = Keypair::generate()->getPublicKey();   // pretend this is a friend

$tx = new Transaction(feePayer: $payer->getPublicKey());
$tx->addInstructions(
    SystemProgram::transfer(
        fromPubkey:  $payer->getPublicKey(),
        toPublicKey: $recipient,
        lamports:    5_000_000,                       // 0.005 SOL
    ),
);

$signature = $connection->sendTransaction($tx, signers: [$payer]);
$connection->confirmTransaction($signature);

echo "https://explorer.solana.com/tx/{$signature}?cluster=devnet\n";
```

Click the URL — your transaction is on-chain.

---

## Core concepts (the 90-second glossary)

| Term | Plain English |
|---|---|
| **Lamport** | Smallest unit of SOL. 1 SOL = 1 000 000 000 (10⁹) lamports. |
| **Pubkey / address** | 32 bytes, base58-encoded. Identifies a wallet, token mint, program, anything. |
| **Keypair** | Pubkey + 64-byte Ed25519 secret. Sign things with the secret. Never log it. |
| **Blockhash** | Recent slot hash that anchors a transaction in time. Expires in ~60–90 s. The SDK fetches one for you if you don't supply one. |
| **Instruction** | One call to a program: program id + ordered accounts + opaque data bytes. |
| **Transaction** | One or more instructions, signed by one or more keypairs, submitted atomically. |
| **Commitment** | How sure you are the network agrees: `processed` (fastest, can roll back) → `confirmed` → `finalized` (irreversible). |
| **RPC** | The HTTP JSON-RPC endpoint at `https://api.mainnet-beta.solana.com` (or devnet/testnet). Call `getBalance`, `sendTransaction`, etc. |
| **PubSub** | The matching WebSocket endpoint at `wss://api.mainnet-beta.solana.com`. Subscribe and get pushed events. |
| **Program** | Solana's word for a smart contract. Has a fixed pubkey. |
| **SPL Token** | The token standard. Every fungible / NFT token is an account owned by the SPL Token program. |
| **Anchor** | A Rust framework most Solana programs are written in. Ships an "IDL" JSON describing its instructions — point this SDK at one and you can call any Anchor program. |

---

## Common recipes

### Read an account

```php
$info = $connection->getAccountInfo('11111111111111111111111111111111');
// returns array with keys: lamports, owner, executable, rentEpoch, data
```

### Read multiple accounts at once

```php
$infos = $connection->getMultipleAccounts(['Acc1...', 'Acc2...', 'Acc3...']);
```

### List the SPL Token accounts owned by a wallet

```php
$tokens = $connection->getTokenAccountsByOwner(
    $payer->getPublicKey(),
    ['programId' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA'],
);
```

### Look up recent signatures for an address

```php
$txs = $connection->getSignaturesForAddress($payer->getPublicKey(), ['limit' => 25]);
foreach ($txs as $tx) {
    echo $tx['signature'], ' ', $tx['blockTime'], "\n";
}
```

### Watch a signature land (WebSocket)

```php
use Collectiq\SolanaPhpSdk\Services\SolanaPubSubClient;
use Collectiq\SolanaPhpSdk\Enum\Network;

$pubsub = new SolanaPubSubClient(network: Network::MAINNET);

$subId = $pubsub->signatureSubscribe(
    $signature,
    ['commitment' => 'finalized'],
);

foreach ($pubsub->listen(maxEvents: 1) as $event) {
    // $event['result']['value']['err'] === null when the tx succeeded
}

$pubsub->unsubscribe($subId);
$pubsub->close();
```

### Verify an Ed25519 signature

```php
use Collectiq\SolanaPhpSdk\PublicKey;

$ok = PublicKey::verify($pubkeyBytes, $messageBytes, $signatureBytes);
```

### Call an Anchor program from its IDL

```php
use Collectiq\SolanaPhpSdk\Anchor\AnchorIdl;

$idl = AnchorIdl::fromFile(__DIR__ . '/my_program.json');

$ix = $idl->instruction('initialize')->build(
    accounts: [
        'state' => $statePubkey,
        'user'  => $payer->getPublicKey(),
        // `systemProgram` etc. are auto-filled from the IDL's fixed `address` field
    ],
    args: [
        'amount' => 1_000_000,
        'label'  => 'hello',
    ],
);

$tx = (new Transaction(feePayer: $payer->getPublicKey()))->addInstructions($ix);
$connection->sendTransaction($tx, signers: [$payer]);
```

Supported IDL types: every primitive in the Anchor 0.30 spec (`u8..u64`,
`i8..i64`, `f32`/`f64`, `bool`, `string`, `bytes`, `pubkey`) plus `vec<T>`,
`array<T, N>`, and `option<T>`. User-defined struct types raise a clear
validation error so you pre-encode them.

---

## Outside Laravel

The SDK doesn't require a Laravel app. Use the bootstrap helper:

```php
require __DIR__ . '/vendor/autoload.php';

use Collectiq\SolanaPhpSdk\Bootstrap;
use Collectiq\SolanaPhpSdk\Connection;

$container  = Bootstrap::createContainer(__DIR__ . '/config/solana-php-sdk.php');
$connection = $container->get(Connection::class);

echo $connection->getSlot();
```

The config file lives at `config/solana-php-sdk.php` once you publish it
(`php artisan vendor:publish --tag=solana-php-sdk-config` inside Laravel, or
copy the package's default) and defines the network and SPL Token program
id.

---

## Reference — every typed RPC method on `Connection`

<details>
<summary><strong>Click to expand</strong></summary>

**Cluster / node info** — `getSlot`, `getTransactionCount`,
`getFirstAvailableBlock`, `getGenesisHash`, `getHealth`, `getVersion`,
`getIdentity`, `getClusterNodes`, `getEpochInfo`, `getEpochSchedule`,
`getHighestSnapshotSlot`, `minimumLedgerSlot`, `getMaxRetransmitSlot`,
`getMaxShredInsertSlot`, `getSlotLeader`, `getSlotLeaders`.

**Blocks / slots** — `getBlockHeight`, `getBlock`, `getBlocks`,
`getBlocksWithLimit`, `getBlockTime`, `getBlockCommitment`,
`getBlockProduction`, `getLeaderSchedule`, `isBlockhashValid`,
`getLatestBlockhash`, `getFeeForMessage`, `getRecentPrioritizationFees`,
`getRecentPerformanceSamples`.

**Accounts** — `getAccountInfo`, `getBalance`, `getMultipleAccounts`,
`getProgramAccounts`, `getMinimumBalanceForRentExemption`,
`getLargestAccounts`.

**Transactions / signatures** — `getTransaction`, `sendTransaction`,
`sendRawTransaction`, `sendVersionedTransaction`, `simulateTransaction`,
`getSignaturesForAddress`, `getSignatureStatuses`, `confirmTransaction`,
`requestAirdrop`.

**Tokens** — `getTokenAccountBalance`, `getTokenSupply`,
`getTokenAccountsByOwner`, `getTokenAccountsByDelegate`,
`getTokenLargestAccounts`.

**Stake / vote / supply** — `getSupply`, `getVoteAccounts`,
`getInflationGovernor`, `getInflationRate`, `getInflationReward`,
`getStakeMinimumDelegation`, `getStakeActivation`.

Any method not yet wrapped is reachable via
`SolanaRpcClient::call($method, $params)`.

</details>

## Reference — PubSub subscriptions

`SolanaPubSubClient` covers every documented WebSocket method:
`accountSubscribe`, `signatureSubscribe`, `slotSubscribe`, `rootSubscribe`,
`logsSubscribe`, `programSubscribe`, `voteSubscribe`, `blockSubscribe`. Use
the generic `subscribe(method, params)` for anything else.

The constructor accepts a `clientFactory: Closure(): WebSocket\Client` so
tests can inject a scripted-reply double instead of opening a real socket.

---

## Troubleshooting

**`Account not found` when reading a fresh wallet.** Solana doesn't allocate
storage until the first deposit. Airdrop / transfer something in first, or
catch `AccountNotFoundException`.

**`Transaction simulation failed: Blockhash not found`.** The blockhash you
attached has expired (~60–90 s). Call `getLatestBlockhash()` again and rebuild
the transaction.

**`Transaction signature verification failed`.** You signed with the wrong
keypair, or modified the transaction after signing. Re-sign after every
mutation. Confirm `feePayer` matches your first signer.

**`SolanaPubSubClient` blocks forever in `listen()`.** Pass `maxEvents:` to
stop after N events, or wrap the loop in a deadline and call `close()`.

**`PublicKey::verify()` returning false even with the right key.** Solana
signatures are over the *raw message bytes*, not a hash. Pass the exact bytes
you signed, with no JSON-encoding or string interpolation in between.

**Airdrop ignored.** Devnet rate-limits per IP and per recipient. Wait a few
minutes or use a faucet website.

---

## Examples

Runnable scripts under `examples/`:

- `examples/quickstart-transfer.php` — airdrop, balance, transfer, confirm.
- `examples/anchor-call.php` — load an IDL, build an instruction, simulate.
- `examples/pubsub-watch-signature.php` — subscribe and block until a tx finalises.

Each script is standalone — `composer install` then `php examples/quickstart-transfer.php`.

---

## Comparison to other Solana clients

| Capability | this SDK | `attestto/solana-php-sdk` | Solnet (.NET) | `@solana/kit` (TS) |
|---|---|---|---|---|
| Typed RPC method coverage | ~90 % | basic | full | full |
| WebSocket PubSub | ✅ full | ❌ | ✅ | ✅ |
| Anchor IDL runtime builder | ✅ | ❌ | partial | ✅ |
| V0 / ALT transactions | ✅ | ❌ | ✅ | ✅ |
| SNS (name service) | ✅ | ❌ | community | community |
| Static analysis posture | PHPStan max + bleeding edge + strict | none | — | — |

---

## Where to ask for help

- **Solana JSON-RPC reference:** https://solana.com/docs/rpc
- **Anchor / IDL docs:** https://www.anchor-lang.com/docs/basics/idl
- **Faucet (devnet SOL):** https://faucet.solana.com
- **Explorer:** https://explorer.solana.com
- **Issues / PRs:** GitHub

---

## Development

```bash
composer install
composer test           # PHPUnit
composer phpstan        # level max + bleeding edge
composer rector         # apply automated refactors
composer rector-dry-run
composer format         # apply Pint
composer qa             # rector + pint + phpstan
```

## Contributing

Issues and PRs welcome. Please run `composer qa` before opening a PR.

## License

MIT — see [LICENSE](LICENSE).
