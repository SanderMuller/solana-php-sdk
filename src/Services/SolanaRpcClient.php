<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Services;

use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Exceptions\InvalidIdResponseException;
use Collectiq\SolanaPhpSdk\Exceptions\MethodNotFoundException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @mixin Http
 * @api
 * @see https://docs.solana.com/developing/clients/jsonrpc-api
 */
final class SolanaRpcClient extends Factory
{
    // The method does not exist / is not available.
    public const int ERROR_CODE_METHOD_NOT_FOUND = -32601;

    private ?string $nonce = null;

    public function __construct(public readonly Network $network = Network::MAINNET)
    {
        parent::__construct();
    }

    /**
     * Build the JSON-RPC payload for $method/$params. Uses a per-instance nonce.
     *
     * @param array<mixed> $params
     * @return array{jsonrpc: string, id: string, method: string, params: array<mixed>}
     */
    public function buildRpc(string $method, array $params = []): array
    {
        $this->nonce ??= Str::uuid()->toString();

        return [
            'jsonrpc' => '2.0',
            'id' => $this->nonce,
            'method' => $method,
            'params' => $params,
        ];
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function call(string $method, array $params = []): mixed
    {
        $payload = $this->buildRpc($method, $params);
        $nonce = $payload['id'];

        $response = $this
            ->throw()
            ->asJson()
            ->acceptJson()
            ->post('/', $payload);

        $error = $response->json('params.error') ?? $response->json('error');

        if (is_array($error)) {
            if (($error['code'] ?? null) === self::ERROR_CODE_METHOD_NOT_FOUND) {
                throw new MethodNotFoundException("API Error: Method {$method} not found.");
            }

            $message = $error['message'] ?? 'Unknown RPC error';
            throw new GenericException(is_string($message) ? $message : 'Unknown RPC error');
        }

        // If 'id' doesn't match the expected value, throw an exception
        if ($response->json('id') !== $nonce) {
            throw new InvalidIdResponseException($nonce);
        }

        return $response->json('result');
    }

    protected function newPendingRequest(): PendingRequest
    {
        return parent::newPendingRequest()
            ->baseUrl($this->network->rpcEndpoint());
    }
}
