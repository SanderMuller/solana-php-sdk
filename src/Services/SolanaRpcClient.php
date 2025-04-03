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

    public function __construct(public readonly Network $network = Network::MAINNET)
    {
        parent::__construct();
    }

    public function call(string $method, array $params): ?array
    {
        $nonce = Str::uuid()->toString();

        $response = $this
            ->throw()
            ->asJson()
            ->acceptJson()
            ->post('/', [
                'jsonrpc' => '2.0',
                'id' => $nonce,
                'method' => $method,
                'params' => $params,
            ]);

        $error = $response->json('params.error') ?: $response->json('error');

        // If response contains an 'error' key, handle it
        if (is_array($error)) {
            if ($error['code'] === self::ERROR_CODE_METHOD_NOT_FOUND) {
                throw new MethodNotFoundException("API Error: Method {$method} not found.");
            }

            throw new GenericException($error['message']);
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
