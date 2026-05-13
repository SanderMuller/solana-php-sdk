<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

/**
 * Pluggable transport contract for JSON-RPC requests.
 *
 * Implementations carry a fully-built JSON-RPC payload to a Solana RPC
 * endpoint and return the decoded JSON response unchanged. Higher layers
 * own request-id / error / result extraction so transports stay focused
 * on a single concern: getting bytes there and back.
 *
 * @api
 */
interface RpcTransport
{
    /**
     * @param array{jsonrpc: string, id: string, method: string, params: array<mixed>} $payload
     * @return array<string, mixed>
     *
     * @throws RpcTransportException on any transport-level failure (HTTP
     *         status >= 400, network error, malformed JSON, etc).
     */
    public function send(array $payload): array;
}
