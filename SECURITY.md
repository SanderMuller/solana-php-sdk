# Security Policy

## Reporting a vulnerability

Open a private advisory on GitHub (`Security` → `Report a vulnerability`) or email `github@scode.nl`. Please do not file public issues for security bugs.

## Supported versions

Only the latest minor release receives security fixes. Pin to a version you can keep updated.

## Cryptography

This SDK uses `ext-sodium` (with `paragonie/sodium_compat` as polyfill fallback) for Ed25519 keypair generation and signing. Keypairs hold raw secret material — never log, serialise, or commit `Keypair` instances or `SecretKey` byte arrays.

`PublicKey::verify()` performs constant-time Ed25519 verification via libsodium. Do **not** roll your own signature checking.

## Network endpoints

`SolanaRpcClient` issues HTTP requests to user-configured RPC endpoints. Treat RPC URLs as secrets when they include API keys (Helius, QuickNode, Triton, etc.) and load them from environment variables rather than committing them to source.

## Supply-chain integrity

The December 2024 `@solana/web3.js` v1 compromise (CVE-2024-54134) showed
how a single maintainer-pinned tag can drain wallets at scale. This
package mitigates that class of risk through:

- **Tag immutability.** GitHub releases are tagged from signed commits;
  retags require force-push to a protected branch.
- **Reproducible Packagist dist.** Composer downloads via
  `--prefer-dist` resolve to a Git archive of the exact tag SHA. Pin
  to a content hash in `composer.lock` and verify on every install
  (`composer install --no-dev` in CI).
- **Recommended verification.** Hosts handling real keys should pin
  via `composer.lock` SHA1 / SHA256 references, run
  `composer audit` in CI, and subscribe to GitHub security advisories
  for this repo. The Sigstore-keyless attestation flow (cosign /
  in-toto provenance) is on the roadmap — track issues tagged
  `security` for status.
