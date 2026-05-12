# Security Policy

## Reporting a vulnerability

Open a private advisory on GitHub (`Security` → `Report a vulnerability`) or email `sander@hihaho.com`. Please do not file public issues for security bugs.

## Supported versions

Only the latest minor release receives security fixes. Pin to a version you can keep updated.

## Cryptography

This SDK uses `ext-sodium` (with `paragonie/sodium_compat` as polyfill fallback) for Ed25519 keypair generation and signing. Keypairs hold raw secret material — never log, serialise, or commit `Keypair` instances or `SecretKey` byte arrays.

`PublicKey::verify()` performs constant-time Ed25519 verification via libsodium. Do **not** roll your own signature checking.

## Network endpoints

`SolanaRpcClient` issues HTTP requests to user-configured RPC endpoints. Treat RPC URLs as secrets when they include API keys (Helius, QuickNode, Triton, etc.) and load them from environment variables rather than committing them to source.
