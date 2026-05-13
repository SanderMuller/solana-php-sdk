<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Enum;

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

    public function pubsubEndpoint(): string
    {
        return match ($this) {
            self::DEVNET => 'wss://api.devnet.solana.com',
            self::TESTNET => 'wss://api.testnet.solana.com',
            default => 'wss://api.mainnet-beta.solana.com',
        };
    }
}
