<?php

namespace Attestto\SolanaPhpSdk\Tests;

use Attestto\SolanaPhpSdk\SolanaRpcClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

class TestCase extends Orchestra
{
    public mixed $config; // Holds the SDK config
    public string $endpoint = SolanaRpcClient::DEVNET_ENDPOINT;

    public function setUp(): void
    {
        $jsonFilePath = dirname(__DIR__) . '/src/Programs/SNS/Constants/config.json';
        $this->config = json_decode(file_get_contents($jsonFilePath), true);
    }

    public function assembleClient(string $rpc_method, array $rpc_params): SolanaRpcClient
    {
        $client = new SolanaRpcClient(
            endpoint: $this->endpoint
        );
        $rpc1 = $client->buildRpc($rpc_method, $rpc_params);
        $mockHandler = new MockHandler([
            new Response(
                200,
                [],
                json_encode($rpc1)
            ),
        ]);

        $requestFactory = $this->createMock(RequestFactoryInterface::class);

        $requestFactory->method('createRequest')
            ->willReturn($this->createMock(RequestInterface::class));

        // Create an instance of SolanaRpcClient with the mocked dependencies
        return new SolanaRpcClient(
            endpoint: $this->endpoint,
            httpClient: new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]),
            requestFactory: $requestFactory,
            streamFactory: $this->createMock(StreamFactoryInterface::class),
        );
    }
}
