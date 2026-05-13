<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests;

use DG\BypassFinals;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Request;
use Orchestra\Testbench\TestCase as Orchestra;
use SanderMuller\SolanaPhpSdk\Bootstrap;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;

abstract class TestCase extends Orchestra
{
    public Container $container;

    public mixed $config;

    protected function setUp(): void
    {
        BypassFinals::enable(bypassReadOnly: false);

        if (str_contains(static::class, '\\Tests\\Feature\\') && ! filter_var(getenv('SOLANA_RUN_FEATURE_TESTS'), FILTER_VALIDATE_BOOLEAN)) {
            self::markTestSkipped('Feature tests require a live Solana RPC. Set SOLANA_RUN_FEATURE_TESTS=1 to run.');
        }

        parent::setUp();

        $this->container = Bootstrap::createContainer(__DIR__ . '/../config/solana-php-sdk.php');

        $jsonFilePath = dirname(__DIR__) . '/src/Programs/SNS/Constants/config.json';
        $raw = file_get_contents($jsonFilePath);
        $this->config = $raw === false ? null : json_decode($raw, true);

        $this->fakeRpc();
    }

    /**
     * Stub RPC responses for unit tests so they never reach the network.
     * Feature tests override by re-binding the SolanaRpcClient.
     */
    protected function fakeRpc(?array $result = null): void
    {
        $client = $this->container->get(SolanaRpcClient::class);

        $client->fake(static fn (Request $request) => SolanaRpcClient::response([
            'jsonrpc' => '2.0',
            'id' => $request->data()['id'] ?? 'stub',
            'result' => $result ?? ['value' => null],
        ]));
    }

    /**
     * Stub RPC responses per JSON-RPC method. Each entry may be a value or a
     * callable; callables receive the inbound JSON-RPC body and may return the
     * next value to emit (useful for advancing through a sequence of replies).
     *
     * @param array<string, mixed|callable(array<string, mixed>): mixed> $byMethod
     */
    protected function fakeRpcByMethod(array $byMethod, mixed $default = null): void
    {
        $client = $this->container->get(SolanaRpcClient::class);

        $this->resetClientStubs($client);

        $client->fake(static function (Request $request) use ($byMethod, $default) {
            $body = $request->data();
            $method = is_string($body['method'] ?? null) ? $body['method'] : '';
            $handler = $byMethod[$method] ?? $default;
            $result = is_callable($handler) ? $handler($body) : $handler;

            return SolanaRpcClient::response([
                'jsonrpc' => '2.0',
                'id' => $body['id'] ?? 'stub',
                'result' => $result,
            ]);
        });
    }

    /**
     * Clear any previously registered Http::fake() stubs on the supplied
     * client so the next fake() call wins. Necessary because Illuminate's
     * Factory accumulates `before` callbacks and the first match short-circuits.
     */
    private function resetClientStubs(SolanaRpcClient $client): void
    {
        (function (): void {
            $this->stubCallbacks = collect();
        })->call($client);
    }

    /**
     * Build a fresh SolanaRpcClient and stub HTTP responses with the supplied JSON-RPC body.
     */
    public function assembleClient(string $rpc_method, array $rpc_params): SolanaRpcClient
    {
        $client = new SolanaRpcClient();

        $client->fake([
            '*' => $client::response([
                'jsonrpc' => '2.0',
                'id' => 'stub',
                ...$rpc_params,
            ]),
        ]);

        return $client;
    }
}
