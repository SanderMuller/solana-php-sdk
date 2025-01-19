<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Enum;

/**
 * @see https://solana.com/docs/core/clusters#on-a-high-level
 */
enum Network: string
{
    case DEVNET = 'devnet';
    case TESTNET = 'testnet';
    case MAINNET = 'mainnet';

    public function rpcEndpoint(): string
    {
        return match ($this) {
            self::DEVNET => 'https://api.devnet.solana.com',
            self::TESTNET => 'https://api.testnet.solana.com',
            default => 'https://api.mainnet-beta.solana.com',
        };
    }
}
