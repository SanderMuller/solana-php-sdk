# Upgrade Guide

Migration steps between minor/major versions of `solana-php-sdk`. Patch
releases never require manual steps. `CHANGELOG.md` is the canonical record of
what changed; this file covers only host-side migration.

Newest at the top. Across-version jumps must complete intermediate sections in
order.

## Unreleased

### Namespace + package rename

The package and its root namespace have moved:

| Before | After |
|---|---|
| `collectiq/solana-php-sdk` | `sandermuller/solana-php-sdk` |
| `Collectiq\SolanaPhpSdk\*` | `SanderMuller\SolanaPhpSdk\*` |

`composer.json`:

```diff
- "collectiq/solana-php-sdk": "^x"
+ "sandermuller/solana-php-sdk": "^x"
```

Code:

```bash
# search-and-replace at the project root
grep -rl 'Collectiq\\SolanaPhpSdk' src tests | \
  xargs sed -i '' 's/Collectiq\\SolanaPhpSdk/SanderMuller\\SolanaPhpSdk/g'
```

No public-API methods changed in this rename — class names, method
signatures, and exception types are identical. The Laravel service
provider auto-discovery picks up the new FQCN on the next
`composer dump-autoload`.
