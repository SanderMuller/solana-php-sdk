# Changelog

All notable changes to `solana-php-sdk` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Namespace + package rename: `Collectiq\SolanaPhpSdk` → `SanderMuller\SolanaPhpSdk`.**
  Composer name moves from `collectiq/solana-php-sdk` to
  `sandermuller/solana-php-sdk`. See `UPGRADING.md` for the migration steps.
- **License: `proprietary` → `MIT`.** Package is now open source. `LICENSE`,
  `composer.json`, and `README.md` updated accordingly.

### Added
- `Programs\MemoProgram` — SPL Memo v2 / v1 instruction builder.
- `Programs\AddressLookupTableProgram` — `createLookupTable` /
  `extendLookupTable` / `freezeLookupTable` / `deactivateLookupTable` /
  `closeLookupTable` plus canonical PDA derivation. Returns the new
  `DataObjects\CreateLookupTableResult` DTO from `createLookupTable()`.
- `Programs\StakeProgram` — initialize, authorize, delegate, deactivate,
  split, withdraw, merge.
- `Programs\VoteProgram` — authorize, withdraw, updateValidatorIdentity,
  updateCommission (operator-facing subset).
- `Programs\Token2022Program` — full SPL-Token-instruction parity against
  the Token-2022 program id (separate ATA derivation included).
- `Util\PriorityFee` — `estimate()` percentile sampler over
  `getRecentPrioritizationFees` + `buildInstructions()` helper emitting
  the `setComputeUnitLimit` + `setComputeUnitPrice` pair.
- `TransactionBuilder` — fluent, sanitize-safe builder that catches missing
  fee payer, missing blockhash, conflicting `isSigner` flags, and signer
  accounts without a matching keypair before the RPC round-trip.
- `Contracts\MessageSigner` — interface for KMS / HSM / hardware-wallet
  signers. Wraps Ed25519 detached-signing through a single
  `signMessage(string): string` method. `Signing\InMemoryMessageSigner`
  ships as the local adapter; hosts implement custom backends.
- `Connection::sendAndConfirmTransaction()` + versioned variant — one-shot
  send + blockhash-expiry-aware poll. `Connection::latestBlockhash()`,
  `Connection::accountInfo()`, `Connection::multipleAccounts()`,
  `Connection::programAccounts()` — typed DTO companions to the raw RPC
  helpers.
- `DataObjects\BlockhashInfo`, `AccountInfo`, `ProgramAccount`,
  `CreateLookupTableResult`, `GpaFilter` — typed wrappers + filter
  builders for the most-used RPC return shapes.
- `Exceptions\BlockhashExpiredException`,
  `ConfirmationTimeoutException`, `TransactionFailedOnChainException`,
  `UnsanitizedTransactionException` — typed exceptions carrying the
  signature and on-chain `err` payload so callers can `catch` by intent
  instead of grepping messages.
- `Enum\Encoding`, `StakeAuthorize`, `VoteAuthorize` — replace magic
  ints / strings. Legacy `int` arguments still accepted.
- `SolanaPubSubClient::enableAutoReconnect()` — exponential-backoff
  reconnect-on-drop with automatic resubscribe of every active
  subscription. `maxRetries: 0` retries forever.

### Added
- `PublicKey::verify()` for Ed25519 signature verification.
- Laravel 13 support.
- PHP 8.3 support (was 8.4-only). `IsProgram` trait rewritten without
  asymmetric visibility / property hooks; `array_any()` replaced with a
  `foreach` loop.
- `AnchorIdl` — runtime Anchor IDL parser + `TransactionInstruction` builder.
  Reads an Anchor 0.30 IDL JSON, parses discriminators (or back-fills via
  `sha256("global:<name>")` for older formats), resolves accounts by name
  (auto-filling fixed `address` accounts like `systemProgram`), and Borsh-
  encodes args via the supplied IDL type tree. `IdlEncoder` supports
  `u8..u64`, `i8..i64`, `f32`/`f64`, `bool`, `string`, `bytes`, `pubkey`,
  plus `vec<T>`, `array<T, N>`, and `option<T>`. User-defined struct types
  raise a clear validation error so the caller pre-encodes them.
- `SolanaPubSubClient` — synchronous JSON-RPC WebSocket client covering the
  full Solana PubSub spec (`accountSubscribe`, `signatureSubscribe`,
  `slotSubscribe`, `rootSubscribe`, `logsSubscribe`, `programSubscribe`,
  `voteSubscribe`, `blockSubscribe`). Built on `phrity/websocket`; injectable
  socket factory keeps unit tests offline. `Network::pubsubEndpoint()` exposes
  the matching `wss://` endpoint for each cluster.
- ~30 new typed JSON-RPC wrappers on `Connection`, taking coverage to roughly
  80 % of the Solana JSON-RPC spec: cluster info (`getSlot`,
  `getTransactionCount`, `getVersion`, `getIdentity`, `getClusterNodes`,
  `getEpochInfo`, `getEpochSchedule`, `getHighestSnapshotSlot`,
  `getGenesisHash`, `getHealth`, `getFirstAvailableBlock`,
  `minimumLedgerSlot`, `getMaxRetransmitSlot`, `getMaxShredInsertSlot`),
  blocks/slots (`getBlock`, `getBlocks`, `getBlocksWithLimit`, `getBlockTime`,
  `getBlockCommitment`, `getBlockProduction`, `getLeaderSchedule`,
  `isBlockhashValid`, `getFeeForMessage`, `getRecentPrioritizationFees`,
  `getRecentPerformanceSamples`), signatures (`getSignaturesForAddress`),
  tokens (`getTokenAccountBalance`, `getTokenSupply`,
  `getTokenAccountsByOwner`, `getTokenLargestAccounts`), and supply/stake/vote
  (`getSupply`, `getVoteAccounts`, `getInflationGovernor`, `getInflationRate`,
  `getInflationReward`, `getStakeActivation`, `getStakeMinimumDelegation`,
  `getLargestAccounts`).

### Fixed
- `VersionedTransaction::sign()` no longer skips `Util\Signer` inputs — both
  `Keypair` and `Signer` are signed through, preventing zero-filled placeholder
  signatures that would be rejected on-chain.
- `VersionedTransaction::serialize()` now writes
  `message.header.numRequiredSignature` as the signature-vector length and
  pads missing entries with zero signatures, so partially-signed multisig
  transactions serialise to a valid wire layout.
- `Borsh::serializeOptionField()` now only treats `null` as `None`. Previously
  falsey-but-present values (`0`, `0.0`, `''`, `[]`) collapsed onto the `None`
  tag and silently dropped data.
- `Util\Signer` no longer claims `HasSecretKey`, resolving a real LSP
  violation that would have crashed at runtime once the class was constructed
  in a context expecting the typed `SecretKey` return.

### Changed
- Tooling skeleton aligned with sibling packages (PHPStan max + bleeding edge,
  Rector with code-quality/dead-code/type-declarations, Pint, PHPUnit 11,
  GitHub Actions for phpstan / pint / rector / tests, Dependabot).
