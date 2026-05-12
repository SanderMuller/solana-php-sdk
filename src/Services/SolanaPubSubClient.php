<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Services;

use Closure;
use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\PublicKey;
use Generator;
use Throwable;
use WebSocket\Client;
use WebSocket\Message\Text;

/**
 * Synchronous JSON-RPC PubSub client for the Solana WebSocket API.
 *
 * Wraps the long-lived `wss://api.*.solana.com` connection with a small
 * subscribe/receive/unsubscribe loop. Designed for batch/worker-style
 * consumption (Laravel scheduler, supervised CLI command) rather than a
 * request/response HTTP context.
 *
 * @see https://solana.com/docs/rpc/websocket
 */
class SolanaPubSubClient
{
    /**
     * The underlying socket. Tests inject a `WebSocket\Client` subclass that
     * scripts canned responses instead of opening a real connection.
     */
    private ?Client $client = null;

    private int $requestIdCounter = 0;

    /**
     * Pending JSON-RPC request id → expected use ('subscribe'|'unsubscribe').
     * Confirmations arrive interleaved with notifications, so we have to
     * dispatch on the `id`/`method` field per frame.
     *
     * @var array<int, string>
     */
    private array $pending = [];

    /**
     * Subscription id → JSON-RPC method that opened it. Needed because each
     * subscription has its own paired `*Unsubscribe` method.
     *
     * @var array<int, string>
     */
    private array $subscriptions = [];

    /**
     * @param Closure(): Client|null $clientFactory Optional factory that
     *        returns a `WebSocket\Client` (or a test subclass that scripts
     *        responses). Defaults to a real `WebSocket\Client` pointed at
     *        `$network->pubsubEndpoint()`.
     */
    public function __construct(
        public readonly Network $network = Network::MAINNET,
        private readonly ?string $endpointOverride = null,
        private readonly int $timeoutSeconds = 60,
        private readonly ?Closure $clientFactory = null,
    ) {}

    /**
     * Subscribe to an arbitrary JSON-RPC notification stream. Returns the
     * server-issued subscription id (use with {@see unsubscribe()}).
     *
     * @param array<int|string, mixed> $params
     */
    public function subscribe(string $method, array $params = []): int
    {
        $id = $this->send($method, $params);
        $this->pending[$id] = 'subscribe';

        $reply = $this->awaitConfirmation($id);

        if (! isset($reply['result']) || ! is_int($reply['result'])) {
            $body = json_encode($reply);
            throw new GenericException("Unexpected subscribe response: {$body}");
        }

        $subscriptionId = $reply['result'];
        $this->subscriptions[$subscriptionId] = $this->subscribeMethodToUnsubscribe($method);

        return $subscriptionId;
    }

    /**
     * Unsubscribe by id. Returns true if the server acknowledges.
     */
    public function unsubscribe(int $subscriptionId): bool
    {
        $method = $this->subscriptions[$subscriptionId]
            ?? throw new GenericException("Unknown subscription id {$subscriptionId}");

        $id = $this->send($method, [$subscriptionId]);
        $this->pending[$id] = 'unsubscribe';

        $reply = $this->awaitConfirmation($id);
        unset($this->subscriptions[$subscriptionId]);

        return ($reply['result'] ?? false) === true;
    }

    /**
     * Yield notification payloads from the open socket. The generator runs
     * until either the socket is closed or `$maxEvents` payloads have been
     * emitted (if non-null). Each emitted value is the JSON-RPC `params`
     * object (`{subscription, result}`).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function listen(?int $maxEvents = null): Generator
    {
        $emitted = 0;

        while (true) {
            $frame = $this->readFrame();

            if ($frame === null) {
                return;
            }

            $method = $frame['method'] ?? null;
            $params = $frame['params'] ?? null;

            if (! is_string($method) || ! is_array($params)) {
                continue;
            }

            /** @var array<string, mixed> $params */
            yield $params;

            $emitted++;

            if ($maxEvents !== null && $emitted >= $maxEvents) {
                return;
            }
        }
    }

    public function close(): void
    {
        if ($this->client instanceof Client) {
            try {
                $this->client->disconnect();
            } catch (Throwable) {
                // best-effort
            }

            $this->client = null;
        }

        $this->subscriptions = [];
        $this->pending = [];
    }

    // ---------------------------------------------------------------------
    // Typed wrappers around the documented PubSub methods.
    // https://solana.com/docs/rpc/websocket
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $options
     */
    public function accountSubscribe(string|PublicKey $account, array $options = ['encoding' => 'base64']): int
    {
        return $this->subscribe('accountSubscribe', [(string) $account, $options]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function signatureSubscribe(string $signature, array $options = ['commitment' => 'confirmed']): int
    {
        return $this->subscribe('signatureSubscribe', [$signature, $options]);
    }

    public function slotSubscribe(): int
    {
        return $this->subscribe('slotSubscribe');
    }

    public function rootSubscribe(): int
    {
        return $this->subscribe('rootSubscribe');
    }

    /**
     * @param 'all'|'allWithVotes'|array{mentions: array<int, string>} $filter
     * @param array<string, mixed> $options
     */
    public function logsSubscribe(string|array $filter = 'all', array $options = ['commitment' => 'confirmed']): int
    {
        return $this->subscribe('logsSubscribe', [$filter, $options]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function programSubscribe(string|PublicKey $programId, array $options = ['encoding' => 'base64']): int
    {
        return $this->subscribe('programSubscribe', [(string) $programId, $options]);
    }

    public function voteSubscribe(): int
    {
        return $this->subscribe('voteSubscribe');
    }

    /**
     * @param string|array{mentionsAccountOrProgram: string} $filter
     * @param array<string, mixed> $options
     */
    public function blockSubscribe(string|array $filter = 'all', array $options = ['commitment' => 'confirmed', 'encoding' => 'base64']): int
    {
        return $this->subscribe('blockSubscribe', [$filter, $options]);
    }

    // ---------------------------------------------------------------------
    // Internals — `protected` so tests can subclass to inject a fake Client.
    // ---------------------------------------------------------------------

    /**
     * Lazily-build the underlying WebSocket client. Subclasses may override
     * to swap in an alternative implementation; tests typically pass a
     * `$clientFactory` constructor argument instead.
     */
    protected function buildClient(): Client
    {
        if ($this->clientFactory instanceof Closure) {
            return ($this->clientFactory)();
        }

        return new Client($this->endpointOverride ?? $this->network->pubsubEndpoint())
            ->setTimeout($this->timeoutSeconds);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function send(string $method, array $params = []): int
    {
        $client = $this->client ??= $this->buildClient();

        $this->requestIdCounter++;
        $id = $this->requestIdCounter;

        $payload = array_filter([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params === [] ? null : $params,
        ], static fn (mixed $v): bool => $v !== null);

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $client->send(new Text($encoded));

        return $id;
    }

    /**
     * Block on the socket until a frame whose `id` matches the supplied
     * request id arrives. Any interleaved notifications are dropped — this
     * helper is only used during the synchronous handshake portion of
     * `subscribe()` / `unsubscribe()`.
     *
     * @return array<string, mixed>
     */
    private function awaitConfirmation(int $expectedId): array
    {
        while (true) {
            $frame = $this->readFrame();

            if ($frame === null) {
                throw new GenericException("Socket closed before confirmation for request {$expectedId}");
            }

            if (($frame['id'] ?? null) === $expectedId) {
                unset($this->pending[$expectedId]);

                if (isset($frame['error'])) {
                    $err = is_array($frame['error']) ? json_encode($frame['error']) : (string) $frame['error'];
                    throw new GenericException("JSON-RPC error on request {$expectedId}: {$err}");
                }

                return $frame;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFrame(): ?array
    {
        $client = $this->client;

        if (! $client instanceof Client) {
            return null;
        }

        try {
            $message = $client->receive();
        } catch (Throwable) {
            return null;
        }

        $body = $message->getContent();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function subscribeMethodToUnsubscribe(string $method): string
    {
        // 'accountSubscribe' → 'accountUnsubscribe' etc.
        return preg_replace('/Subscribe$/', 'Unsubscribe', $method)
            ?? throw new GenericException("Cannot derive unsubscribe method for {$method}");
    }
}
