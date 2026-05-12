# Changelog

All notable changes to `solana-php-sdk` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **License: `proprietary` → `MIT`.** Package is now open source. `LICENSE`,
  `composer.json`, and `README.md` updated accordingly.

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
