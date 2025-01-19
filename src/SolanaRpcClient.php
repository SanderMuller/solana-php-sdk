<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Exceptions\InvalidIdResponseException;
use Collectiq\SolanaPhpSdk\Exceptions\MethodNotFoundException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Str;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * @see https://docs.solana.com/developing/clients/jsonrpc-api
 */
final readonly class SolanaRpcClient
{
    // The method does not exist / is not available.
    public const int ERROR_CODE_METHOD_NOT_FOUND = -32601;

    // Reserved for implementation-defined server-errors.
    // -32000 to -32099 is server error - no const.

    public UuidInterface $nonce;

    public function __construct(
        public Network                 $network = Network::DEVNET,
        public ClientInterface         $httpClient = new GuzzleClient(),
        public RequestFactoryInterface $requestFactory = new HttpFactory(),
    ) {
        $this->nonce = Str::uuid();
    }

    /**
     * @throws GenericException
     * @throws InvalidIdResponseException
     * @throws MethodNotFoundException|ClientExceptionInterface
     */
    public function call(string $method, array $params = []): mixed
    {
        $body = json_encode($this->buildRpc($method, $params));
        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => $body,
        ];

        $response = $this->httpClient->request('POST', $this->network->rpcEndpoint(), $options);

        $resp_body = $response->getBody()->getContents();
        $resp_object = json_decode((string) $resp_body, true);

        $this->validateResponse($resp_object, $method);

        return $resp_object['result'] ?? null;
    }

    public function buildRpc(string $method, array $params): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->nonce->toString(),
            'method' => $method,
            'params' => $params,
        ];
    }

    /**
     * @throws GenericException
     * @throws InvalidIdResponseException
     * @throws MethodNotFoundException
     */
    private function validateResponse(array $body, string $method): void
    {
        // If response contains an 'error' key, handle it
        if (isset($body['params']['error']) || isset($body['error'])) {
            $error = $body['params']['error'] ?: $body['error'];
            if ($error['code'] === self::ERROR_CODE_METHOD_NOT_FOUND) {
                throw new MethodNotFoundException("API Error: Method {$method} not found.");
            }

            throw new GenericException($error['message']);
        }

        // If 'id' doesn't match the expected value, throw an exception
        if ($body['id'] !== $this->nonce->toString()) {
            throw new InvalidIdResponseException($this->nonce->toString());
        }
    }
}
