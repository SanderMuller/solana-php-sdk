<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Testing;

use Illuminate\Http\Client\Request;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;

/**
 * Drop-in `SolanaRpcClient` test-double for hosts building unit tests
 * against the SDK. Records every outbound JSON-RPC call and dispatches
 * responses by method name, falling back to a default.
 *
 * This is **not** an in-process SVM (no transaction execution, no state
 * tracking) — it is a deterministic fake for assertions about *which*
 * RPC calls the SDK makes and with *what* shape, plus a way to script
 * responses for SDK code paths that branch on them. A real SVM-backed
 * harness (litesvm-style) would need a PHP implementation of the Solana
 * VM and remains out of scope.
 *
 * Example:
 *
 * ```php
 * use SanderMuller\SolanaPhpSdk\Testing\InMemoryRpcStub;
 *
 * $stub = InMemoryRpcStub::create()->script([
 *     'getBalance' => ['value' => 5_000_000],
 *     'getLatestBlockhash' => [
 *         'context' => ['slot' => 1],
 *         'value' => ['blockhash' => '111…', 'lastValidBlockHeight' => 250],
 *     ],
 * ]);
 *
 * $container->bind(SolanaRpcClient::class, fn () => $stub->client());
 *
 * // … exercise the SDK …
 *
 * self::assertSame(['getBalance', 'getLatestBlockhash'], $stub->methodCalls());
 * ```
 */
final class InMemoryRpcStub
{
    /** @var array<string, mixed|callable(array<string, mixed>): mixed> */
    private array $script = [];

    private mixed $default = null;

    /** @var list<string> */
    private array $methodCalls = [];

    /** @var list<array<string, mixed>> */
    private array $bodies = [];

    private readonly SolanaRpcClient $client;

    public function __construct()
    {
        $this->client = new SolanaRpcClient();
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Register a script entry per JSON-RPC method. Values may be the
     * literal response payload or a callable that receives the inbound
     * body and returns the next payload (useful for advancing through a
     * sequence of replies).
     *
     * @param array<string, mixed|callable(array<string, mixed>): mixed> $byMethod
     */
    public function script(array $byMethod, mixed $default = null): self
    {
        $this->script = $byMethod;
        $this->default = $default;
        $this->install();

        return $this;
    }

    /**
     * Reset recorded call history without dropping the active script.
     */
    public function resetHistory(): void
    {
        $this->methodCalls = [];
        $this->bodies = [];
    }

    public function client(): SolanaRpcClient
    {
        return $this->client;
    }

    /**
     * @return list<string> JSON-RPC method names in invocation order.
     */
    public function methodCalls(): array
    {
        return $this->methodCalls;
    }

    /**
     * @return list<array<string, mixed>> Full JSON-RPC bodies in order.
     */
    public function bodies(): array
    {
        return $this->bodies;
    }

    private function install(): void
    {
        // Drop any prior `fake()` callbacks before re-installing — Factory's
        // `stubCallbacks` collection short-circuits on first match, so a
        // second `script()` call would otherwise still hit the original
        // closure (which holds the old `$script`/`$default` via separate
        // captures even though they live on `$this`).
        $this->resetStubCallbacks();

        $script = &$this->script;
        $default = &$this->default;
        $methodCalls = &$this->methodCalls;
        $bodies = &$this->bodies;

        $this->client->fake(static function (Request $request) use (&$script, &$default, &$methodCalls, &$bodies) {
            $body = $request->data();
            $method = is_string($body['method'] ?? null) ? $body['method'] : '';
            $methodCalls[] = $method;
            /** @var array<string, mixed> $body */
            $bodies[] = $body;

            $handler = $script[$method] ?? $default;
            $result = is_callable($handler) ? $handler($body) : $handler;

            return SolanaRpcClient::response([
                'jsonrpc' => '2.0',
                'id' => $body['id'] ?? 'stub',
                'result' => $result,
            ]);
        });
    }

    /**
     * Reach into Illuminate's `Factory::$stubCallbacks` and clear the
     * collection. The property is private; a closure rebound to the
     * client's scope is the only knob the framework exposes.
     */
    private function resetStubCallbacks(): void
    {
        (function (): void {
            $this->stubCallbacks = collect();
        })->call($this->client);
    }
}
