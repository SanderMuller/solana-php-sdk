# solana-php-sdk

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/solana-php-sdk.svg?style=flat-square)](https://packagist.org/packages/sandermuller/solana-php-sdk)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/solana-php-sdk/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/solana-php-sdk/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/sandermuller/solana-php-sdk.svg?style=flat-square)](https://packagist.org/packages/sandermuller/solana-php-sdk)
[![License](https://img.shields.io/packagist/l/sandermuller/solana-php-sdk.svg?style=flat-square)](LICENSE)

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
| Programs | System, SPL Token, **Token-2022**, ComputeBudget, AddressLookupTable, Stake, Vote, Memo, Metaplex, DID-Sol, SNS | `Programs\*` |
| Send & confirm | Priority-fee estimator + `sendAndConfirmTransaction` helper with blockhash-expiry detection | `Util\PriorityFee`, `Connection::sendAndConfirmTransaction` |
| Pluggable RPC transports | Multi-endpoint fallback / round-robin / exponential-backoff retry | `Rpc\FallbackTransport`, `Rpc\RoundRobinTransport`, `Rpc\RetryTransport` |
| Structured `sendTransaction` errors | Decoded `TransactionError` + `InstructionError` enums, program logs, units consumed | `Exceptions\SendTransactionError`, `Errors\TransactionErrorDecoder` |
| Auto compute-budget + priority fee | Simulate → derive CU limit → inject `setComputeUnitLimit` + `setComputeUnitPrice` on build | `Fees\AutoComputeBudget`, `Fees\PriorityFeeStrategy`, `TransactionBuilder::withAutoComputeBudget` |
| In-memory RPC stub + Pest macros | `InMemoryRpcStub::script([...])` swaps the RPC client; `toBeConfirmed` / `toHaveCustomCode` / `toBeInstructionError` expectations — works in any test runner, Laravel not required | `Testing\InMemoryRpcStub`, `Testing\PestExpectations` |
| Laravel facade + queue job + artisan commands | Lives in the sister wrapper [`sandermuller/laravel-solana-sdk`](https://github.com/SanderMuller/laravel-solana-sdk): `Solana::fake()`, `ConfirmTransactionJob::dispatch($sig, $lvbh)`, env-driven config, 7 artisan commands (balance, account, transaction, …) | (wrapper) |
| PDA / ATA helpers | One-line `Pda::find` + `Ata::derive` (Token-2022 aware via `Ata::derive2022`) | `Util\Pda`, `Util\Ata` |
| Anchor | Parse any Anchor IDL at runtime → typed `TransactionInstruction` builders | `Anchor\AnchorIdl` |

## Who this is for

- **PHP devs new to Solana.** Start with the [5-minute quickstart](#5-minute-quickstart) — you'll send a real on-chain transfer.
- **Laravel apps.** The package auto-registers a service provider; resolve `Connection` straight from the container.
- **Standalone PHP scripts.** A one-liner bootstrap (`Bootstrap::createContainer`) wires up the same container outside Laravel.

---

## Install

```bash
composer require sandermuller/solana-php-sdk
```

The package self-registers `SanderMuller\SolanaPhpSdk\ServiceProvider` via
Laravel's [package discovery](https://laravel.com/docs/packages#package-discovery).
Standalone PHP doesn't need anything extra — see
["Outside Laravel"](#outside-laravel) below.

Requirements: PHP 8.3+ with `ext-sodium` enabled (every mainstream PHP distro
ships it). Composer pulls in `paragonie/sodium_compat` as a polyfill backup.

> **Building a Laravel app?** Install the sister wrapper instead — it adds the
> `Solana` facade, `ConfirmTransactionJob`, env-driven config, and 7 artisan
> commands (balance, account, transaction, fees, health, …) on top of this SDK:
> ```bash
> composer require sandermuller/laravel-solana-sdk
> ```
> Repo: <https://github.com/SanderMuller/laravel-solana-sdk>. You get this SDK
> as a transitive dep — no need to require both.

---

## 5-minute quickstart

We'll go from zero to a signed-and-broadcast SOL transfer on devnet.

### 1. Generate (or load) a keypair

```php
use SanderMuller\SolanaPhpSdk\Keypair;

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
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Enum\Network;

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
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\Transaction;

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

#### Sign from a KMS / HSM / hardware wallet

For hosts that cannot expose secret bytes to PHP (KMS-backed keys,
Ledger devices, remote signing services), implement
`Contracts\MessageSigner`:

```php
use SanderMuller\SolanaPhpSdk\Contracts\MessageSigner;
use SanderMuller\SolanaPhpSdk\PublicKey;

final class AwsKmsSigner implements MessageSigner
{
    public function __construct(
        private readonly string $kmsKeyId,
        private readonly PublicKey $publicKey,
        private readonly KmsClient $kms,
    ) {}

    public function getPublicKey(): PublicKey { return $this->publicKey; }

    public function signMessage(string $message): string
    {
        // Return 64-byte Ed25519 signature from your KMS / HSM.
        return $this->kms->signEd25519($this->kmsKeyId, $message);
    }
}

$tx = TransactionBuilder::new()
    ->feePayer($publicKey)
    ->recentBlockhash($connection->latestBlockhash())
    ->addInstruction($instruction)
    ->addMessageSigner(new AwsKmsSigner(/* … */))
    ->build();
```

For the in-process case, wrap a `Keypair` in
`Signing\InMemoryMessageSigner` to get the same interface.

##### Sketch: HashiCorp Vault Transit

```php
final class VaultTransitSigner implements MessageSigner
{
    public function __construct(
        private readonly string $keyName,
        private readonly PublicKey $publicKey,
        private readonly VaultClient $vault,
    ) {}

    public function getPublicKey(): PublicKey { return $this->publicKey; }

    public function signMessage(string $message): string
    {
        $reply = $this->vault->write(
            "transit/sign/{$this->keyName}/ed25519",
            ['input' => base64_encode($message), 'hash_algorithm' => 'none'],
        );

        // Vault returns "vault:v1:<base64-signature>"; strip prefix + decode.
        return base64_decode(explode(':', $reply['data']['signature'])[2]);
    }
}
```

##### Sketch: Ledger hardware wallet

The Ledger Solana app expects a base58-derivation-path-prefixed APDU.
Wrap any USB/HID transport (e.g. via a sidecar process bridged over
stdio) and implement `signMessage()` as a single `INS_SIGN_MESSAGE`
APDU round-trip.

#### Sanitize-safe builder

Most transaction failures across Solana SDKs hit the same opaque error:
`"Transaction failed to sanitize accounts offsets correctly"`. The fluent
`TransactionBuilder` catches the causes locally so you never see that
message:

```php
use SanderMuller\SolanaPhpSdk\TransactionBuilder;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;

$tx = TransactionBuilder::new()
    ->feePayer($payer->getPublicKey())
    ->recentBlockhash($connection->latestBlockhash())   // BlockhashInfo or string
    ->addInstruction(SystemProgram::transfer($payer->getPublicKey(), $to, 1))
    ->addSigner($payer)
    ->build();
```

`build()` throws `UnsanitizedTransactionException` if the fee payer is
missing, the blockhash is missing, an instruction marks an account
`isSigner` with no matching keypair, or two instructions disagree on
whether the same account is a signer. Every detected reason is surfaced
at once on `$exception->reasons`.

#### One-shot send-and-confirm

Most apps want both steps in one call, with blockhash-expiry built in:

```php
$status = $connection->sendAndConfirmTransaction(
    transaction: $tx,
    signers:     [$payer],
);
// $status->confirmationStatus === 'confirmed'
```

The helper fetches `getLatestBlockhash`, signs, sends, and polls
`getSignatureStatuses` until the commitment is reached or the blockhash
expires — no manual retry loop.

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
$info = $connection->accountInfo('11111111111111111111111111111111');

// Typed DTO — no array-shape memorisation needed.
$info->lamports;     // int
$info->owner;        // PublicKey
$info->executable;   // bool
$info->data;         // Buffer — base64 already decoded
$info->data?->toArray();
```

Legacy `$connection->getAccountInfo(...)` still returns the raw RPC array.

### Read multiple accounts at once

```php
$infos = $connection->multipleAccounts(['Acc1...', 'Acc2...', 'Acc3...']);

foreach ($infos as $info) {
    if ($info === null) continue;   // missing slot
    echo $info->owner->toBase58(), ' ', $info->lamports, "\n";
}
```

### List the SPL Token accounts owned by a wallet

```php
$tokens = $connection->getTokenAccountsByOwner(
    $payer->getPublicKey(),
    ['programId' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA'],
);
```

### Paginate a large program scan

For programs with too many accounts to fetch in a single
`getProgramAccounts` round-trip, partition the scan by account
`dataSize`. The generator emits a `ProgramAccount` per row across one
RPC call per bucket:

```php
$rows = $connection->programAccountsPaged(
    programId: SplTokenProgram::TOKEN_PROGRAM_ID,
    dataSizes: [82, 165],   // mints + token accounts
);

foreach ($rows as $row) {
    echo $row->pubkey->toBase58(), "\n";
}
```

### Scan a program for accounts matching a filter

```php
use SanderMuller\SolanaPhpSdk\DataObjects\GpaFilter;
use SanderMuller\SolanaPhpSdk\Programs\SplTokenProgram;

// All SPL Token accounts holding a specific mint (offset 0..32 = mint pubkey).
$rows = $connection->programAccounts(
    programId: SplTokenProgram::TOKEN_PROGRAM_ID,
    filters: [
        GpaFilter::dataSize(165),                 // SPL Token Account = 165 bytes
        GpaFilter::memcmp(0, $mintPublicKey),     // mint at offset 0
    ],
);

foreach ($rows as $row) {
    echo $row->pubkey->toBase58(), ' ', $row->account->lamports, "\n";
    // $row->account->data is a base64-decoded Buffer
}
```

`GpaFilter::memcmp()` accepts a `PublicKey`, `Buffer`, or raw base58
string — the most common drift point across Solana SDKs (offset / encoding
swap) is locked in the builder.

### Look up recent signatures for an address

```php
$txs = $connection->getSignaturesForAddress($payer->getPublicKey(), ['limit' => 25]);
foreach ($txs as $tx) {
    echo $tx['signature'], ' ', $tx['blockTime'], "\n";
}
```

### Watch a signature land (WebSocket)

```php
use SanderMuller\SolanaPhpSdk\Services\SolanaPubSubClient;
use SanderMuller\SolanaPhpSdk\Enum\Network;

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

#### Survive socket drops

Long-running consumers (indexers, queue workers) can opt in to
transparent reconnect — the client respawns the socket, replays every
active subscription, and resumes yielding notifications without the
caller noticing the gap:

```php
$pubsub->enableAutoReconnect(maxRetries: 0, baseDelayMs: 500);  // 0 = forever
```

Backoff is exponential with full jitter (`baseDelayMs * 2^attempt`,
randomly shrunk to avoid thundering-herd on a cluster outage).
Subscription ids change across the reconnect — the new id ships inside
each notification's `subscription` field, so don't key per-id state on
the integer if you enable this.

### Add a memo to a transaction

```php
use SanderMuller\SolanaPhpSdk\Programs\MemoProgram;

$tx->addInstructions(
    MemoProgram::build('order-id=42', signers: [$payer->getPublicKey()]),
);
```

The Memo program writes raw UTF-8 bytes into the transaction log so
off-chain indexers can correlate transactions with application data.
`signers` is optional — list any public keys that must be verified as
transaction signers (Memo v2 behaviour).

### Pay the priority fee the network is currently asking

```php
use SanderMuller\SolanaPhpSdk\Util\PriorityFee;

[$limit, $price] = PriorityFee::buildInstructions(
    connection:        $connection,
    computeUnitLimit:  200_000,
    writableAccounts:  [$payer->getPublicKey()],   // tighter estimate
    percentile:        0.75,                       // beat 75% of recent traffic
);

$tx = new Transaction(feePayer: $payer->getPublicKey());
$tx->addInstructions($limit, $price, /* your real instructions */);
```

Samples `getRecentPrioritizationFees` and returns the requested
percentile in micro-lamports per compute unit. Returns `0` when the
network is idle — no fee is necessary in that regime.

### Token-2022 mint extensions

```php
use SanderMuller\SolanaPhpSdk\Programs\Token2022Program;

$program = new Token2022Program();

// 1. Allocate the mint account (system program), then queue extensions
//    BEFORE InitializeMint — Token-2022 ordering rule.
$tx = TransactionBuilder::new()
    ->feePayer($payer->getPublicKey())
    ->recentBlockhash($connection->latestBlockhash())
    ->addInstructions(
        // Allocate + assign owner via SystemProgram::createAccount (omitted)
        $program->createInitializeNonTransferableMintInstruction($mint->getPublicKey()),
        $program->createInitializeMintCloseAuthorityInstruction($mint->getPublicKey(), $payer->getPublicKey()),
        $program->createInitializeTransferFeeConfigInstruction(
            mint: $mint->getPublicKey(),
            transferFeeConfigAuthority: $payer->getPublicKey(),
            withdrawWithheldAuthority: $payer->getPublicKey(),
            transferFeeBasisPoints: 250,            // 2.5%
            maximumFee: 1_000_000,
        ),
        // Finally initialize the mint
        $program->createInitializeMint2Instruction(
            mint: $mint->getPublicKey(),
            decimals: 6,
            mintAuthority: $payer->getPublicKey(),
            freezeAuthority: null,
        ),
    )
    ->addSigner($payer)
    ->addSigner($mint)
    ->build();
```

Memo-transfer (require a Memo instruction in every incoming transfer):

```php
// Enable both initializes the extension and turns the requirement on.
$tx->addInstructions(
    $program->createMemoTransferToggleInstruction($tokenAccount, $owner->getPublicKey(), enable: true),
);

// Later — turn it off:
$program->createMemoTransferToggleInstruction($tokenAccount, $owner->getPublicKey(), enable: false);
```

### Send a Token-2022 transfer

```php
use SanderMuller\SolanaPhpSdk\Programs\Token2022Program;

$program = new Token2022Program();

$ix = $program->createTransferCheckedInstruction(
    source:      $sourceAta,
    mint:        $mint,
    destination: $destAta,
    owner:       $owner->getPublicKey(),
    amount:      1_000_000,
    decimals:    6,
);
```

Token-2022 inherits every core discriminator from legacy SPL Token — the
program-id is the only difference. The ATA address differs between the
two programs even for the same `(owner, mint)` pair, so derive it
through `$program->getAssociatedTokenAddressSync()` rather than reusing
a legacy-Token ATA.

### Build a v0 transaction with an Address Lookup Table

```php
use SanderMuller\SolanaPhpSdk\Programs\AddressLookupTableProgram;

// 1. Create the lookup table (one-time, returns a PDA address + bump)
$slot = $connection->getSlot();
$create = AddressLookupTableProgram::createLookupTable($authority, $payer, $slot);

// 2. Extend it with up to 256 addresses (across calls)
$extend = AddressLookupTableProgram::extendLookupTable(
    lookupTable: $create['lookupTableAddress'],
    authority:   $authority,
    payer:       $payer,
    addresses:   [$mint, $programId, /* ... */],
);
```

Use the resulting `lookupTableAddress` as an `AddressLookupTableAccount`
when compiling a `MessageV0`.

### Verify an Ed25519 signature

```php
use SanderMuller\SolanaPhpSdk\PublicKey;

$ok = PublicKey::verify($pubkeyBytes, $messageBytes, $signatureBytes);
```

### Call an Anchor program from its IDL

```php
use SanderMuller\SolanaPhpSdk\Anchor\AnchorIdl;

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

## Using with Laravel

This SDK works inside a Laravel app out of the box via auto-discovery —
`Connection` resolves straight from the container. For the full
Laravel-flavoured experience (facade, queue job, env-driven config, artisan
commands), install the wrapper instead:

```bash
composer require sandermuller/laravel-solana-sdk
```

| What the wrapper adds | Why it's separate from this SDK |
|---|---|
| `Solana` facade with 62 typed `@method` declarations | Laravel-only contract; framework-agnostic SDK shouldn't ship Facade subclasses |
| `ConfirmTransactionJob` (Queueable, Dispatchable) | Laravel Queue contracts; events (`TransactionConfirmed` / `TransactionExpired`) stay here in the SDK |
| Env-driven config + `php artisan vendor:publish` | Laravel config publishing |
| 7 artisan commands: `solana:balance`, `solana:account`, `solana:transaction`, `solana:fees`, `solana:health`, `solana:airdrop`, `solana:tokens` | Laravel Console contracts |
| Pre-wired `Solana::fake()` for tests | One-liner over this SDK's `InMemoryRpcStub` |

Repo: <https://github.com/SanderMuller/laravel-solana-sdk>. The wrapper requires
this SDK as a transitive dep — install just the wrapper, not both.

---

## Outside Laravel

The SDK doesn't require a Laravel app. Use the bootstrap helper:

```php
require __DIR__ . '/vendor/autoload.php';

use SanderMuller\SolanaPhpSdk\Bootstrap;
use SanderMuller\SolanaPhpSdk\Connection;

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

### Compared to the canonical Solana SDKs across all languages

This SDK is built against the recurring pain points operators hit in
`@solana/web3.js`, `@solana/kit`, `solana-py` / `solders`, `solana-go`,
and `solana-sdk` / `anchor-client`. The matrix below is the short
version; see the rationale beneath it.

| Capability | this SDK | `@solana/kit` (TS) | `@solana/web3.js` (TS) | `solana-py` + `solders` | `solana-go` | `solana-sdk` + `anchor-client` (Rust) |
|---|---|---|---|---|---|---|
| Pluggable RPC transports (fallback / round-robin / retry) | ✅ | ✅ | ❌ | ❌ | ❌ | partial |
| Structured `SendTransactionError` (decoded `InstructionError`, logs, units) | ✅ | ✅ | partial | ❌ | partial | ✅ |
| Auto compute-unit limit + priority-fee injection on build | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Sanitize-safe transaction builder | ✅ | ✅ (type-level) | ❌ | ❌ | ❌ | ✅ |
| KMS / HSM / hardware-wallet signer abstraction | ✅ | ✅ | community | ❌ | community | ✅ |
| Queue-based confirmation + lifecycle events | ✅ ([via wrapper](https://github.com/SanderMuller/laravel-solana-sdk)) | ❌ | ❌ | ❌ | ❌ | ❌ |
| In-process test fakes + assertion helpers | ✅ (Pest macros in SDK; `Solana::fake()` in wrapper) | partial | ❌ | partial | ❌ | partial |
| Token-2022 extensions | ✅ | partial | partial | partial | partial | ✅ |

#### What we built against

- **Blockhash expiry is typed, not opaque.** `sendAndConfirmTransaction`
  tracks `lastValidBlockHeight` and trips `BlockhashExpiredException`
  instead of swallowing the failure or silently re-signing — the #1
  cross-SDK footgun in `web3.js` issues #1987 / #2361.
- **Structured errors instead of raw RPC strings.** `sendTransaction` /
  `simulateTransaction` failures raise a `SendTransactionError`
  carrying a decoded `TransactionError`
  (`InstructionError(2, Custom(6000))`, `BlockhashNotFound`,
  `InsufficientFundsForRent { account_index }`, …), full program logs,
  and `unitsConsumed`.
- **Pluggable RPC transports.** Add a Helius primary + Triton backup +
  public fallback in a config block — the SDK round-robins or fails
  over on transient errors. Per-endpoint exponential-backoff retry
  decorator included.
- **Auto compute-budget + priority-fee on build.**
  `TransactionBuilder::withAutoComputeBudget()` simulates, reads
  `unitsConsumed`, scales by a safety buffer, and injects both
  `setComputeUnitLimit` + `setComputeUnitPrice` before signing. No
  other SDK ships this by default — every other ecosystem leaves it
  as a Helius / Quicknode blog-post recipe.
- **Laravel-native async story** (in [`sandermuller/laravel-solana-sdk`](https://github.com/SanderMuller/laravel-solana-sdk)).
  `ConfirmTransactionJob::dispatch($sig, $lvbh)` hands the long-tail
  confirmation phase to a queue worker, then fires `TransactionConfirmed` /
  `TransactionExpired` events (defined here in the SDK). Inherits retries +
  DLQ from your queue backend — Kit's async-iterator API cannot match this
  on PHP-FPM.
- **`InMemoryRpcStub` + Pest expectations.** `InMemoryRpcStub::script([...])`
  feeds a deterministic RPC reply queue. Pest macros
  (`toBeConfirmed`, `toHaveCustomCode`, `toBeInstructionError`) ship in this
  SDK — usable from any test runner. The wrapper adds `Solana::fake()` as
  a Laravel-flavoured one-liner over the same stub.
  Other SDKs stand up a local validator just to write a unit test.
- **Sanitize-safe builder.** Catches duplicate-account flag conflicts,
  missing signers, and orphaned `isSigner` flags locally before the
  RPC round-trip — so you never see "Transaction failed to sanitize
  accounts offsets correctly", the single most-reported cross-SDK
  error.
- **PDA + ATA one-liners.** `Pda::find($programId, $seeds)` and
  `Ata::derive($owner, $mint)` (with a `Token2022Program::TOKEN_PROGRAM_ID`
  argument or the `Ata::derive2022()` shortcut). Most SDKs hardcode
  the legacy SPL Token program in the ATA helper.

---

## Roadmap

Tracked separately as GitHub issues; the high-level next steps are:

- **In-process test validator** (litesvm-style) — drive transactions
  against a deterministic SVM execution layer without an RPC round-trip.
  Out of scope for this release; the Rust `litesvm` crate is the
  reference. Track issues tagged `harness`.
- **Token-2022 advanced extensions** — confidential transfer (ZK
  proofs), transfer hook (CPI to a host program), metadata pointer.
  Initialize-only versions of transfer-fee, mint-close-authority,
  permanent-delegate, non-transferable, immutable-owner, memo-transfer
  already ship.
- **Concrete KMS adapters** — AWS KMS, GCP KMS, HashiCorp Vault, Ledger.
  The `MessageSigner` interface is stable; hosts implement against their
  preferred backend (see README sketches above).

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
