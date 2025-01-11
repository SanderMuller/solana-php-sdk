<?php declare(strict_types=1);

namespace Attestto\SolanaPhpSdk;

use GuzzleHttp\Psr7\Message;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;


use Attestto\SolanaPhpSdk\Exceptions\GenericException;
use Attestto\SolanaPhpSdk\Exceptions\InvalidIdResponseException;
use Attestto\SolanaPhpSdk\Exceptions\MethodNotFoundException;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @see https://docs.solana.com/developing/clients/jsonrpc-api
 */
class SolanaRpcClient
{
    public const LOCAL_ENDPOINT = 'http://localhost:8899';
    public const DEVNET_ENDPOINT = 'https://api.devnet.solana.com';
    public const TESTNET_ENDPOINT = 'https://api.testnet.solana.com';
    public const MAINNET_ENDPOINT = 'https://api.mainnet-beta.solana.com';

    /**
     * Per: https://www.jsonrpc.org/specification
     */
    // Invalid JSON was received by the server.
    // An error occurred on the server while parsing the JSON text.
    public const ERROR_CODE_PARSE_ERROR = -32700;
    // The JSON sent is not a valid Request object.
    public const ERROR_CODE_INVALID_REQUEST = -32600;
    // The method does not exist / is not available.
    public const ERROR_CODE_METHOD_NOT_FOUND = -32601;
    // Invalid method parameter(s).
    public const ERROR_CODE_INVALID_PARAMETERS = -32602;
    // Internal JSON-RPC error.
    public const ERROR_CODE_INTERNAL_ERROR = -32603;
    // Reserved for implementation-defined server-errors.
    // -32000 to -32099 is server error - no const.

    public readonly int $randomKey;

    public function __construct(
        public readonly string                              $endpoint = self::DEVNET_ENDPOINT,
        public readonly ClientInterface                     $httpClient = new GuzzleClient(),
        public readonly RequestFactoryInterface             $requestFactory = new HttpFactory(),
        public readonly StreamFactoryInterface|Message|null $streamFactory = null,
    )
    {
        $this->randomKey = random_int(0, 99999999);
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

        $response = $this->httpClient->request('POST', $this->endpoint, $options);

        $resp_body = $response->getBody()->getContents();
        $resp_object = json_decode($resp_body, true);

        $this->validateResponse($resp_object, $method);

        return $resp_object['result'] ?? null;
    }

    public function buildRpc(string $method, array $params): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->randomKey,
            'method' => $method,
            'params' => $params,
        ];
    }

    /**
     * @throws GenericException
     * @throws InvalidIdResponseException
     * @throws MethodNotFoundException
     */
    protected function validateResponse(array $body, string $method): void
    {
        // If response contains an 'error' key, handle it
        if (isset($body['params']['error']) || isset($body['error'])) {
            $error = $body['params']['error'] ?: $body['error'];
            if ($error['code'] === self::ERROR_CODE_METHOD_NOT_FOUND) {
                throw new MethodNotFoundException("API Error: Method $method not found.");
            }

            throw new GenericException($error['message']);
        }

        // If 'id' doesn't match the expected value, throw an exception
        if ($body['id'] !== $this->randomKey) {
            throw new InvalidIdResponseException((string) $this->randomKey);
        }
    }
}
