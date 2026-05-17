# Transaction Introspection Decoder

## Overview

A subsystem that takes a raw `getTransaction` RPC response (or a freshly
sent `Transaction` / `VersionedTransaction`) and produces a fully
decoded tree: per-instruction program id + role-tagged accounts +
data buffer + IDL-resolved arg names where an IDL is registered + the
inner-instruction CPI tree + parsed program logs (invoke depth,
`Program log:` lines, `consumed X of Y compute units`, return data).
This is the indexer/MEV-grade capability that `solana-go`
(Gagliardetto) is loved for and that every other SDK either skips or
implements piecemeal — closing this gap moves the PHP SDK from "best
for application devs" to "best across all use cases" relative to other
ecosystems.

---

## 1. Data Model

New namespace: `src/Tx/Decoded/` for the DTO tree.

### `DecodedTransactionSource` (enum)

Discriminates between the two construction paths so consumers never
have to guess whether a null field means "not yet known" or "RPC
returned no data".

```php
enum DecodedTransactionSource: string
{
    case Rpc = 'rpc';            // built from getTransaction response
    case Outbound = 'outbound';  // built from an in-process Transaction|VersionedTransaction
}
```

### `DecodedTransaction`

```php
final readonly class DecodedTransaction
{
    /**
     * @param list<DecodedInstruction>         $instructions
     * @param list<DecodedLogEvent>            $logs       always [] when source = Outbound
     * @param list<string>                     $accountKeys post-ALT-expansion order: static + loaded writable + loaded readonly
     * @param list<string>                     $signers     subset of $accountKeys
     * @param list<int>                        $writableIndexes  indexes into $accountKeys
     * @param array<string, mixed>|null        $meta        raw `meta` envelope; null for Outbound or when RPC omits it
     * @param list<string>                     $loadedWritableAddresses ALT-loaded writable addresses (V0); [] for legacy
     * @param list<string>                     $loadedReadonlyAddresses ALT-loaded readonly addresses (V0); [] for legacy
     */
    public function __construct(
        public DecodedTransactionSource $source,
        public ?string $signature,                          // null for unsigned Outbound
        public ?int    $slot,
        public ?int    $blockTime,
        public ?int    $fee,
        public ?int    $computeUnitsConsumed,
        public mixed   $err,                                // null on success, decoded TransactionError on failure, undefined for Outbound (always null)
        public array   $accountKeys,
        public array   $signers,
        public array   $writableIndexes,
        public array   $loadedWritableAddresses,
        public array   $loadedReadonlyAddresses,
        public array   $instructions,
        public array   $logs,
        public ?array  $meta,
    ) {}
}
```

Nullability rules — explicit, do NOT collapse:

|                      | source = Rpc | source = Outbound |
|----------------------|--------------|-------------------|
| `signature`          | non-null     | null if unsigned, otherwise the first signature |
| `slot` / `blockTime` | non-null     | null              |
| `fee`                | non-null     | null              |
| `computeUnitsConsumed` | nullable (RPC may omit) | null    |
| `err`                | nullable (null = success) | null  |
| `logs`               | `list<DecodedLogEvent>` (possibly empty) | `[]` |
| `meta`               | raw array, possibly `null` per RPC | `null` |
| `loadedWritableAddresses` / `loadedReadonlyAddresses` | populated for V0 with ALTs, else `[]` | populated when the source tx already resolved its lookups, else `[]` |

### `DecodedInstruction`

```php
final readonly class DecodedInstruction
{
    /**
     * @param list<DecodedAccountRef>   $accounts        positional account list, role-tagged
     * @param list<DecodedInstruction>  $innerInstructions CPI tree
     * @param array<string, mixed>|null $idlArgs         null when no IDL registered for $programId
     * @param string|null               $idlName         instruction name as resolved from IDL
     */
    public function __construct(
        public int     $index,                           // top-level slot in tx, or "i.j" via $path
        public string  $path,                            // "2" or "2.1.0" for nested CPIs
        public ?int    $stackHeight,                     // null when RPC does not report it (no log-depth fallback in v1)
        public string  $programId,
        public string  $data,                            // raw bytes (binary string)
        public string  $dataBase58,                      // bs58-encoded copy for log/UI rendering
        public array   $accounts,
        public array   $innerInstructions,
        public ?string $idlName,
        public ?array  $idlArgs,
    ) {}
}
```

### `DecodedAccountRef`

```php
final readonly class DecodedAccountRef
{
    public function __construct(
        public int    $index,                            // index into DecodedTransaction::$accountKeys
        public string $pubkey,                           // bs58
        public bool   $isSigner,
        public bool   $isWritable,
        public ?string $idlName,                         // resolved from IDL accounts array if present
    ) {}
}
```

### `DecodedLogEvent`

Discriminated union via a `kind` tag (string enum-like). Variants:

- `kind = 'invoke'`     — `programId`, `depth`. Source: `Program <pid> invoke [<depth>]`.
- `kind = 'success'`    — `programId`. Source: `Program <pid> success`.
- `kind = 'failure'`    — `programId`, `error`. Source: `Program <pid> failed: <err>`.
- `kind = 'log'`        — `programId`, `message`. Source: `Program log: <msg>`.
- `kind = 'data'`       — `programId`, `bytes` (binary string). Source: `Program data: <b64>`.
- `kind = 'consumed'`   — `programId`, `consumed`, `budget`. Source: `Program <pid> consumed X of Y compute units`.
- `kind = 'returnData'` — `programId`, `bytes`. Source: `Program return: <pid> <b64>`.

```php
final readonly class DecodedLogEvent
{
    public function __construct(
        public string $kind,
        public ?string $programId,
        public ?int $depth,
        public ?string $message,
        public ?string $bytes,
        public ?int $consumed,
        public ?int $budget,
        public ?string $error,
    ) {}
}
```

(One DTO with optional fields keeps consumer code simple; alternative
of one class per variant is overkill for the cardinality.)

### `ProgramDecoder` + `IdlRegistry`

V1 ships an Anchor-IDL-backed decoder. To leave the door open for
non-Anchor programs (System / SPL Token / Memo — none of which use an
8-byte discriminator) we expose a contract instead of binding to
`AnchorIdl` directly.

```php
interface ProgramDecoder
{
    public function programId(): string;

    /**
     * @return array{name: string, args: array<string, mixed>}|null  null when this decoder
     *         does not recognise the instruction shape (caller falls back to raw bytes).
     */
    public function decode(string $instructionData, IdlAccountContext $accounts): ?array;
}

final class IdlRegistry
{
    public function register(ProgramDecoder $decoder): void;
    public function registerAnchor(AnchorIdl $idl): void;     // sugar: wraps in AnchorProgramDecoder
    public function forProgram(string $programId): ?ProgramDecoder;
    public function known(): array;            // list<string> of registered program ids
}
```

V1 ships `AnchorProgramDecoder` (the 8-byte discriminator path).
Non-Anchor decoders for System / SPL Token / Memo are explicitly **out
of scope for v1** — flagged for v2. The spec's prior reference to SPL
Memo's `addMemo` as a Phase 3 test target is removed; the Phase 3
fixture must come from a real Anchor program (the existing
`tests/fixtures/anchor-idl.json` should suffice if it carries args).

Bound `scoped` in the Laravel container (single registry per request)
and as a singleton outside Laravel via `Bootstrap`. Hosts populate
during boot.

---

## 2. Decoder Architecture

Three coordinating classes under `src/Tx/Decoded/`:

- `TransactionDecoder` — the entry point. Two factory methods:
  - `::fromRpcResponse(array $rpc, IdlRegistry $registry): DecodedTransaction` for `getTransaction` shape.
  - `::fromOutbound(Transaction|VersionedTransaction $tx, IdlRegistry $registry): DecodedTransaction` for the about-to-send case (no logs/meta, but the instruction tree decodes).
- `InstructionDecoder` — owns per-instruction decode: byte data → `data` + `dataBase58`, accounts → `DecodedAccountRef[]`, optional IDL lookup → `idlName` + `idlArgs`.
- `LogDecoder` — consumes the `meta.logMessages` array and returns a flat `list<DecodedLogEvent>`. The CPI tree is rebuilt from `meta.innerInstructions[].instructions[]` (which already groups by parent index), not from log depth — logs are sometimes truncated by the validator while inner-instructions are authoritative.

### V0 + Address Lookup Table expansion

Solana V0 transactions reference accounts in compiled instructions via
indexes into `staticAccountKeys + loadedWritableAddresses +
loadedReadonlyAddresses` — in that exact order. `meta.loadedAddresses
= { writable: [...], readonly: [...] }` is what `getTransaction`
returns alongside the message; the decoder MUST merge them in that
order before resolving instruction-account indexes. Failing to do so
yields silently wrong account pubkeys for any instruction that touches
an ALT entry — a hard correctness bug.

For Outbound source, `loadedWritableAddresses` /
`loadedReadonlyAddresses` come from the `addressTableLookups` field on
the source `VersionedTransaction` only if the SDK has already resolved
them (we ship `AddressLookupTableAccount` for this — but resolution
requires a `Connection` call). When unresolved, the decoder leaves the
addresses arrays empty and stamps `DecodedAccountRef::$pubkey` for
each ALT-referenced slot with a synthetic
`__alt_unresolved:<table>:<index>` sentinel string. Consumers can
detect that prefix and either resolve themselves or render
"unresolved" — never silently fabricate a wrong pubkey.

### Inner-instruction grouping

`meta.innerInstructions` arrives shaped as

```
[
  { index: 0, instructions: [<inner0>, <inner1>, …] },
  { index: 2, instructions: [<inner0>] },
]
```

The decoder walks each top-level slot, attaching the matching
`innerInstructions` block (or `[]` if absent) and assigning each inner
the `path = "<top>.<n>"`. The `stackHeight` field comes straight from
`stackHeight` on the RPC payload when present (Solana RPC 1.17+);
otherwise it is left `null`. The earlier "derive from log depth"
fallback is **dropped from v1** — logs interleave, truncate, and
ambiguate across sibling CPIs to the same program, so any derived
value would silently lie. Consumers needing depth on older RPC
versions must upgrade their RPC or read it from their own log parse.

### IDL-resolved args

`InstructionDecoder` uses the registered `AnchorIdl` to:

1. Look up the discriminator by reading the first 8 bytes of `data`
   and matching against `AnchorIdl::instructions[*]->discriminator`.
2. Decode the trailing arg bytes via `IdlEncoder::decode()` (a new
   method — currently only `encode()` exists on `IdlEncoder`).
3. Map the IDL's `accounts` array to the positional `accounts` list to
   populate `DecodedAccountRef::$idlName`.

When no IDL is registered or the discriminator does not match,
`idlName` and `idlArgs` stay null. The raw `data` / `dataBase58` are
always populated so downstream tooling still has bytes to display.

### Failure modes

- Discriminator mismatch → fall back to anonymous instruction (raw data only).
- Borsh decode error during arg decode → catch + log + leave `idlArgs` null. Do not bubble; one malformed instruction must not poison a batch decode.
- `meta.logMessages` truncated by the validator → emit what we parsed plus a synthetic `kind: 'log'` event with `message: '[truncated]'`.

---

## 3. New IDL Decoding Surface

`src/Anchor/IdlEncoder.php` currently has `encode()` only. Add
`decode(IdlInstruction $ix, BinaryReader $reader): array` mirroring
its shape, walking the `args` schema and returning a name-keyed array.

This is the largest behavioural change in the spec. Borsh primitives
already handled by `BinaryReader`:

- `u8`–`u128`, `i8`–`i128`, `f32`, `f64`, `bool`, `string`, `bytes`, `pubkey`
- `vec<T>`, `array<T; N>`, `option<T>`

User-defined `{defined: {name}}` references resolve via
`AnchorIdl::$types` — a new `IdlTypeDecoder` recursively walks the
shape. Tests cover the common cases (nested structs, enums-as-tagged-unions, options).

---

## 4. Connection Integration

`Connection::getTransaction` already returns `?TransactionStatement` —
that DTO stays as the lightweight default for callers who don't want
the full decode. Two new typed accessors:

```php
public function decodeTransaction(string $signature, IdlRegistry $registry, ?Commitment $commitment = null): ?DecodedTransaction;
public function decodeMostRecent(string $signature, ?IdlRegistry $registry = null, ...): ?DecodedTransaction;
```

Both call the RPC with `encoding: 'json'` (parsed) + `maxSupportedTransactionVersion: 0` so V0 transactions decode through the same path.

When `IdlRegistry` is null, the registry resolves from the container
(`scoped`-bound) so Laravel hosts pick up their global registry
automatically.

---

## 5. Laravel Wiring

- `IdlRegistry` bound `scoped` in `ServiceProvider`. Hosts populate via
  a one-liner in their `AppServiceProvider::boot()`:

  ```php
  $this->app->scoped(IdlRegistry::class, function () {
      $registry = new IdlRegistry();
      $registry->register(AnchorIdl::fromFile(base_path('idl/jupiter.json')));
      $registry->register(AnchorIdl::fromFile(base_path('idl/raydium.json')));
      return $registry;
  });
  ```

- Config key `solana-php-sdk.idls`: an array of `programId => path` pairs
  the package auto-loads during `Bootstrap::createContainer`. Falsy /
  missing → empty registry.

---

## 6. Use Cases (what unlocks)

- **Indexers**: persist every instruction + decoded args + inner-CPIs to a database. Today, hosts hand-roll borsh parsing per program. This is the
  Gagliardetto comparable feature.
- **Block explorers / dashboards**: render an instruction tree in a UI
  with proper account labels.
- **MEV / on-chain analytics**: walk inner instructions to detect swap
  routes through Jupiter / Raydium / Orca.
- **Test assertions** (Phase 4): `expect($sig)->toHaveCalled(programId: $pid, instruction: 'initialize', args: ['amount' => 1_000_000], onlyTopLevel: true)` — a Pest expectation that runs the decoder over `getTransaction` and asserts an instruction with the given IDL-resolved args appears in the tree. `programId` is **required**; instruction names are not globally unique (the same name appears across different Anchor programs and across top-level / inner CPI calls), so a name-only assertion would mask decoder bugs. `onlyTopLevel` defaults to false; pass true to scope the match to depth-1 instructions. An optional `atPath: '2.1'` arg lets callers pin the match to an exact instruction slot.

---

## Implementation

### Phase 1: DTOs + LogDecoder (Priority: HIGH)

- [x] Create `src/Tx/Decoded/DecodedTransaction.php`, `DecodedInstruction.php`, `DecodedAccountRef.php`, `DecodedLogEvent.php` per §1.
- [x] Create `src/Tx/Decoded/LogDecoder.php` — pure function `decode(list<string> $logs): list<DecodedLogEvent>`. Regex-based; reject malformed lines silently.
- [x] Create `src/Tx/Decoded/IdlRegistry.php` — in-memory map.
- [x] Tests — every `DecodedLogEvent::$kind` covered with a fixture log line. Edge cases: truncated logs, nested `invoke`/`success` interleavings, multiple `Program return:` events.

### Phase 2: Instruction decoder without IDL (Priority: HIGH)

- [x] Create `src/Tx/Decoded/InstructionDecoder.php` covering bytes + accounts only (no IDL resolution yet).
- [x] Create `src/Tx/Decoded/TransactionDecoder.php` with `::fromRpcResponse()` — handles legacy + V0, **including ALT address expansion via `meta.loadedAddresses` in `static + writable + readonly` order**, inner-instruction grouping, signers / writable extraction.
- [x] Wire `Connection::decodeTransaction($sig, ?IdlRegistry)` — null registry → empty registry default. Pass `maxSupportedTransactionVersion: 0`.
- [x] Tests — golden-fixture `getTransaction` payloads:
    - legacy SOL transfer,
    - token transfer with inner CPIs,
    - V0 with ALTs where an instruction touches a loaded writable address AND a loaded readonly address,
    - response with `meta = null` (assert `DecodedTransaction::$meta === null`, no crash).
  Assert the tree, account roles, fee + computeUnitsConsumed, ALT-expanded pubkey resolution.

### Phase 3: IDL arg decoding (Priority: HIGH)

- [x] Create `src/Anchor/AnchorProgramDecoder.php` implementing `ProgramDecoder` — reads the first 8 bytes as discriminator, matches against `AnchorIdl::instructions[*]->discriminator`, decodes args via the new `IdlEncoder::decode()`.
- [x] Extend `src/Anchor/IdlEncoder.php` with `decode(IdlInstruction, BinaryReader): array`.
- [x] Create `src/Anchor/IdlTypeDecoder.php` — recursively handles `{defined: ...}` references, nested structs, tagged-union enums, options.
- [x] Wire registry lookup into `InstructionDecoder` — `IdlRegistry::forProgram($programId)?->decode(...)` → `DecodedInstruction::$idlArgs` / `$idlName` / per-account `$idlName`.
- [x] Tests — decode an **Anchor**-program transaction shipped as a fixture under `tests/fixtures/` (a small Anchor counter is fine). Assert arg names + values, plus the no-IDL-registered fallback (raw bytes only). **Do NOT use SPL Memo as a fixture** — it has no Anchor discriminator and is explicitly out of scope until non-Anchor decoders ship.

### Phase 4: Pest assertions + docs (Priority: MEDIUM)

- [ ] Extend `src/Testing/PestExpectations.php` with `toHaveCalled(string $programId, string $instruction, ?array $args = null, bool $onlyTopLevel = false, ?string $atPath = null)`. `programId` is **required** — without it the assertion is ambiguous across programs.
- [ ] Add `Solana::decoded($sig)` facade shortcut.
- [ ] README "Decode a transaction" recipe in the existing recipes section.
- [ ] Tests — Pest expectation against a stubbed `getTransaction` response with a registered IDL. Include a regression test where two different programs in one tx expose the same instruction name; the assertion must distinguish them via `programId`.

### Phase 5: outbound decode (Priority: LOW)

- [ ] `TransactionDecoder::fromOutbound(Transaction|VersionedTransaction, IdlRegistry)` — the about-to-send case. No logs / meta / fee — but the instruction tree, accounts, and IDL args decode. Use case: pre-flight sanity dump in CI.
- [ ] Tests — outbound transfer decodes; outbound Anchor call decodes args correctly.

---

## Open Questions

1. **Where should `IdlRegistry` registration ergonomics live?** Option A: bind once in `AppServiceProvider`, hosts call `$registry->register($idl)` explicitly. Option B: config-driven (`solana-php-sdk.idls => ['<programId>' => 'path/to/idl.json']`) with auto-load. Spec proposes both; we may want to ship only A in v1 and let B follow once the registry API stabilises.

2. **Discriminator collisions across registered IDLs.** Two programs can in principle ship overlapping 8-byte discriminators in their instruction set (rare but possible). The decoder dispatches on `programId` first, so this is a non-issue for accuracy — but should we lint at registration time and warn? Cost: one pass over `instructions` per registered IDL.

3. **ALT pubkey resolution on the outbound path.** A `VersionedTransaction` with `addressTableLookups` cannot resolve target pubkeys without a `Connection` round-trip. Spec settles on emitting `__alt_unresolved:<table>:<index>` sentinels — confirm this won't conflict with any legitimate base58 pubkey (it can't, since `__` is not base58). Add a runtime validator?

4. **Sentinel for unsigned outbound `signature`.** Spec says `signature: null` when the source is Outbound and not yet signed. Consumers that key on `signature` for dedupe will get null collisions. Document — or generate a deterministic hash-of-message sentinel? Tradeoff: hash-of-message is stable across the same builder output, but anyone who passes "signed" later then mutates the tx triggers a different hash.

5. **Borsh enum representation in `idlArgs`.** Anchor encodes tagged-union enums as `{ variantName: { ...payload } }` in IDL JSON; should `idlArgs` mirror that shape (array with single string key) or flatten to `[$variant, $payload]`? Spec proposes mirroring (matches JS / Rust ecosystems).

6. **Non-Anchor `ProgramDecoder` v2 scope.** v1 ships only `AnchorProgramDecoder`. The contract exists so System / SPL Token / SPL Memo / SPL Token-2022 / ComputeBudget decoders can plug in later. Should v1 ship a single hand-written `SystemProgramDecoder` to validate the contract shape, or keep v1 strictly Anchor and defer the whole non-Anchor track?

---

## Resolved Questions

5. **Borsh enum representation in `idlArgs`?** **Decision:** mirror Anchor / JS / Rust — `{variantName: payload}` for variants with fields, `{variantName: true}` for unit variants. **Rationale:** matches what downstream consumers already expect from cross-language tooling; lets PHP callers use `array_key_first($enum)` to discriminate without sniffing a `kind` tag. Tuple-payload variants emit a positional list; struct-payload variants emit a name-keyed map.

## Findings

### Phase 1

- **`DecodedLogEvent` gained `KIND_TRUNCATED` and `KIND_UNKNOWN` variants** beyond the seven listed in §1. The spec deliberately notes "fail-open" parsing under "Failure modes" but doesn't enumerate the fallback `kind`; the implementation needed an explicit tag so consumers can filter unknown lines without reparsing the raw string. `KIND_TRUNCATED` matches `^Log truncated` (validator's clip marker) and is also the spec's prescribed synthetic event for that case.
- **`LogDecoder` tracks an `invoke`/`success` program-id stack** so `Program log:` and `Program data:` lines attribute to the most recent active frame. Spec §1 lists `programId` as a field on those variants without saying how to derive it; the stack is the natural source. Lines that arrive before any `invoke` (orphan logs) leave `programId` null — tested.
- **`IdlRegistry::registerAnchor()` ships a stub for Phase 3.** The method exists today and `register()` works for hand-written `ProgramDecoder`s; calling `registerAnchor()` before Phase 3's `AnchorProgramDecoder` ships throws a `LogicException` with a precise pointer. This lets hosts wire registration calls now and have them light up automatically when Phase 3 lands.
- **Test fixed**: an early test built `Program <pid> return: <pid> <b64>` lines, which is not the on-chain format. Solana emits `Program return: <pid> <b64>`. The regex is correct; the test was wrong and now uses the real shape.

### Phase 2

- **Role computation is centralised** in `TransactionDecoder::buildAccountRoleMap()`. Spec §2 lists the merge order (static + loaded writable + loaded readonly) but leaves the per-index `isSigner` / `isWritable` derivation implicit. The implementation uses the standard Solana header arithmetic:
  - Signed writable: `[0, numRequiredSignatures - numReadonlySigned)`
  - Signed readonly: `[numRequiredSignatures - numReadonlySigned, numRequiredSignatures)`
  - Unsigned writable: `[numRequiredSignatures, staticCount - numReadonlyUnsigned)`
  - Unsigned readonly: `[staticCount - numReadonlyUnsigned, staticCount)`
  - All loaded-writable entries are writable/non-signer; all loaded-readonly entries are readonly/non-signer.
- **Inner instructions stay one level deep in v1.** The RPC's `innerInstructions[].instructions[]` is already a flat list (with `stackHeight` annotating depth). The spec's `"2.1.0"` example was speculative; v1 emits `"<top>.<n>"` paths and surfaces nested CPI via `stackHeight`. Re-grouping into a true nested tree is deferred — no consumer needs it yet, and a non-recursive structure preserves the RPC's authoritative ordering.
- **`Connection::decodeTransaction` always passes `maxSupportedTransactionVersion: 0`** so V0 transactions decode through the same path. Older RPCs that don't recognise the param will refuse the request — that's documented as a Phase 2 requirement and we don't apologise for it.
- **`InstructionDecoder` is `@internal`.** The class lives under `src/Tx/Decoded/` but `TransactionDecoder` is the public entry point. Hosts implementing custom transaction shapes (out of scope) would consume the same DTOs but call `InstructionDecoder::decode` directly.
- **Upstream deprecation in `stephenhill/base58`** triggers on PHP 8.5: implicitly nullable parameter without `?` prefix. The base58 library's call site is in vendor code; we cannot fix it here. Tests run green with the deprecation noted by PHPUnit but not failing.

### Phase 3

- **`IdlEncoder` gained `decode(IdlInstruction, BinaryReader, ?AnchorIdl): array` + `decodeType(string|array, BinaryReader, ?IdlTypeDecoder): mixed`.** The `decodeType` split exists so a caller without an `AnchorIdl` in hand can still decode primitives + composites — defined references require an IDL and throw a clear error pointing the caller at `IdlEncoder::decode()`.
- **`IdlTypeDecoder` is the recursive walker** for `{defined: ...}` references, user-defined structs, and tagged-union enums. Anchor encodes variant tags as `u8`; `IdlTypeDecoder::decodeEnum` reads the tag, looks up the variant by position in `variants[]`, and emits `{variantName => payload}` matching the JS / Rust ecosystem convention (Open Question Q5 resolved as proposed). Variants with no fields decode to `{variantName => true}` so callers can `array_key_first()` regardless of payload.
- **Anchor enum tuple-vs-struct variants both supported.** A variant's `fields` array can be heterogeneous: positional (each entry a type string or shape) or named (each entry `{name, type}`). The decoder peeks at the first field — if it's a bare type string or a non-`name`-keyed array, the variant is treated as a tuple and emits a positional payload list; otherwise it's a struct with name-keyed payload.
- **Failure-mode contract honoured.** `AnchorProgramDecoder::decode()` returns `null` when:
  - the data is shorter than the 8-byte discriminator,
  - the discriminator doesn't match any instruction in the IDL,
  - or the Borsh decode throws mid-stream.
  In all three cases the caller (`InstructionDecoder`) falls back to raw `data` / `dataBase58` and continues — one malformed instruction never poisons a batch decode (spec §"Failure modes").
- **Per-account IDL name attachment** flows through `decode()`'s `accountNames` field. `InstructionDecoder` rebuilds the `DecodedAccountRef` list with the resolved IDL names. The IDL's account positional order is canonical — extra on-chain accounts beyond what the IDL declares stay un-named (idlName === null).
- **`IdlRegistry::registerAnchor()` lit up automatically.** Phase 1's deferred stub is gone; the method now directly wraps the IDL in an `AnchorProgramDecoder`.
