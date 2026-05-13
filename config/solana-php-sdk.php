<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Config;

use SanderMuller\SolanaPhpSdk\Enum\Network;

return [
    'token_program_id' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',

    'network' => Network::DEVNET,

    /*
    |--------------------------------------------------------------------------
    | RPC transports
    |--------------------------------------------------------------------------
    |
    | By default the SDK posts each JSON-RPC request to the configured
    | network's public endpoint. For production deployments — where you
    | want a primary provider (Helius, Triton, …) plus one or two
    | fallbacks — supply a `transport` array:
    |
    |   'transport' => [
    |       'mode'    => 'fallback',                 // or 'round_robin'
    |       'urls'    => ['https://mainnet.helius-rpc.com/?api-key=…', 'https://api.mainnet-beta.solana.com'],
    |       'headers' => ['Authorization' => 'Bearer …'],
    |       'timeout' => 30.0,                       // seconds
    |       'retry'   => ['max_attempts' => 3, 'base_delay_ms' => 100, 'max_delay_ms' => 2_000],
    |   ];
    |
    | Retry is applied per-endpoint before fallback advances. Leave the
    | key absent or null to keep the single-endpoint default.
    */

    'transport' => null,
];
