<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Services;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SanderMuller\SolanaPhpSdk\Enum\Network;
use SanderMuller\SolanaPhpSdk\Exceptions\InvalidIdResponseException;
use SanderMuller\SolanaPhpSdk\Exceptions\MethodNotFoundException;
use SanderMuller\SolanaPhpSdk\Exceptions\RpcException;
use SanderMuller\SolanaPhpSdk\Rpc\RpcTransport;

/**
 * @mixin Http
 * @api
 * @see https://docs.solana.com/developing/clients/jsonrpc-api
 */
final class SolanaRpcClient extends Factory
{
    // The method does not exist / is not available.
    public const int ERROR_CODE_METHOD_NOT_FOUND = -32601;

    /**
     * Pass a {@see RpcTransport} to route requests through a fallback /
     * round-robin / retry stack. When null, the client posts directly to
     * the network's default RPC endpoint via Laravel's HTTP client.
     */
    public function __construct(
        public readonly Network $network = Network::MAINNET,
        private readonly ?RpcTransport $transport = null,
    ) {
        parent::__construct();
    }

    /**
     * Build the JSON-RPC payload for $method/$params. Each call gets a
     * fresh UUID so a stale proxy response cannot satisfy the id-check on
     * a later request.
     *
     * @param array<mixed> $params
     * @return array{jsonrpc: string, id: string, method: string, params: array<mixed>}
     */
    public function buildRpc(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => Str::uuid()->toString(),
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

        $body = $this->transport instanceof RpcTransport
            ? $this->transport->send($payload)
            : $this->sendViaHttp($payload);

        $error = $body['error'] ?? null;

        if (is_array($error)) {
            if (($error['code'] ?? null) === self::ERROR_CODE_METHOD_NOT_FOUND) {
                throw new MethodNotFoundException("API Error: Method {$method} not found.");
            }

            $message = is_string($error['message'] ?? null) ? $error['message'] : 'Unknown RPC error';
            $code = is_int($error['code'] ?? null) ? $error['code'] : 0;

            throw new RpcException($message, $code, $error['data'] ?? null);
        }

        if (($body['id'] ?? null) !== $nonce) {
            throw new InvalidIdResponseException($nonce);
        }

        return $body['result'] ?? null;
    }

    /**
     * @param array{jsonrpc: string, id: string, method: string, params: array<mixed>} $payload
     * @return array<string, mixed>
     */
    private function sendViaHttp(array $payload): array
    {
        $response = $this->createPendingRequest()
            ->throw()
            ->asJson()
            ->acceptJson()
            ->post('/', $payload);

        $body = $response->json();

        /** @var array<string, mixed> $body */
        return is_array($body) ? $body : [];
    }

    protected function newPendingRequest(): PendingRequest
    {
        return parent::newPendingRequest()
            ->baseUrl($this->network->rpcEndpoint());
    }
}
