<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Services;

use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Exceptions\GenericException;
use Collectiq\SolanaPhpSdk\Services\SolanaPubSubClient;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebSocket\Client;
use WebSocket\Message\Message;
use WebSocket\Message\Text;

/**
 * Drive `SolanaPubSubClient` against a hand-rolled fake `WebSocket\Client`
 * that records sent frames and replays canned receive frames in order.
 *
 * Each scripted reply is a JSON-encoded JSON-RPC frame (confirmation or
 * notification). The client should walk through them deterministically:
 *  - `subscribe()` writes one frame and expects the next reply to carry the
 *    matching `id` plus a `result` sub-id.
 *  - `listen()` then consumes whatever notifications come after, until
 *    `$maxEvents` is reached or the script runs out.
 */
final class SolanaPubSubClientTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    private function frame(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $raw): array
    {
        self::assertIsString($raw);
        $value = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($value);

        /** @var array<string, mixed> $value */
        return $value;
    }

    #[Test]
    public function subscribe_returns_server_subscription_id_and_listen_yields_notifications(): void
    {
        $fake = new FakeWebsocket([
            $this->frame(['jsonrpc' => '2.0', 'id' => 1, 'result' => 42]),
            $this->frame([
                'jsonrpc' => '2.0',
                'method' => 'slotNotification',
                'params' => ['subscription' => 42, 'result' => ['slot' => 100, 'parent' => 99, 'root' => 98]],
            ]),
            $this->frame([
                'jsonrpc' => '2.0',
                'method' => 'slotNotification',
                'params' => ['subscription' => 42, 'result' => ['slot' => 101, 'parent' => 100, 'root' => 99]],
            ]),
            $this->frame(['jsonrpc' => '2.0', 'id' => 2, 'result' => true]), // unsubscribe ack
        ]);

        $client = new SolanaPubSubClient(network: Network::MAINNET, clientFactory: static fn (): FakeWebsocket => $fake);

        $subId = $client->slotSubscribe();
        self::assertSame(42, $subId);
        self::assertCount(1, $fake->sent);
        $firstSent = $this->decode($fake->sent[0]);
        self::assertSame('slotSubscribe', $firstSent['method']);

        $events = [];
        foreach ($client->listen(maxEvents: 2) as $event) {
            $events[] = $event;
        }

        self::assertCount(2, $events);
        $firstResult = $events[0]['result'];
        $secondResult = $events[1]['result'];
        self::assertIsArray($firstResult);
        self::assertIsArray($secondResult);
        self::assertSame(100, $firstResult['slot']);
        self::assertSame(101, $secondResult['slot']);

        self::assertTrue($client->unsubscribe($subId));
        self::assertCount(2, $fake->sent);
        $secondSent = $this->decode($fake->sent[1]);
        self::assertSame('slotUnsubscribe', $secondSent['method']);
        self::assertSame([42], $secondSent['params']);
    }

    #[Test]
    public function subscribe_propagates_jsonrpc_error(): void
    {
        $fake = new FakeWebsocket([
            $this->frame([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => ['code' => -32602, 'message' => 'Invalid params'],
            ]),
        ]);

        $client = new SolanaPubSubClient(clientFactory: static fn (): FakeWebsocket => $fake);

        $this->expectException(GenericException::class);
        $this->expectExceptionMessage('Invalid params');

        $client->slotSubscribe();
    }

    #[Test]
    public function subscribe_throws_when_socket_closes_before_confirmation(): void
    {
        $fake = new FakeWebsocket([]); // no replies at all

        $client = new SolanaPubSubClient(clientFactory: static fn (): FakeWebsocket => $fake);

        $this->expectException(GenericException::class);
        $this->expectExceptionMessageMatches('/Socket closed before confirmation/');

        $client->slotSubscribe();
    }

    #[Test]
    public function unsubscribe_rejects_unknown_subscription_id(): void
    {
        $fake = new FakeWebsocket([]);
        $client = new SolanaPubSubClient(clientFactory: static fn (): FakeWebsocket => $fake);

        $this->expectException(GenericException::class);
        $this->expectExceptionMessageMatches('/Unknown subscription id 999/');

        $client->unsubscribe(999);
    }

    #[Test]
    public function account_subscribe_passes_pubkey_and_options(): void
    {
        $fake = new FakeWebsocket([
            $this->frame(['jsonrpc' => '2.0', 'id' => 1, 'result' => 7]),
        ]);
        $client = new SolanaPubSubClient(clientFactory: static fn (): FakeWebsocket => $fake);

        $client->accountSubscribe('3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX');

        $sent = $this->decode($fake->sent[0]);
        self::assertSame('accountSubscribe', $sent['method']);
        self::assertSame('3Wnd5Df69KitZfUoPYZU438eFRNwGHkhLnSAWL65PxJX', $sent['params'][0]);
        self::assertSame(['encoding' => 'base64'], $sent['params'][1]);
    }

    #[Test]
    public function logs_subscribe_with_mentions_filter(): void
    {
        $fake = new FakeWebsocket([
            $this->frame(['jsonrpc' => '2.0', 'id' => 1, 'result' => 13]),
        ]);
        $client = new SolanaPubSubClient(clientFactory: static fn (): FakeWebsocket => $fake);

        $client->logsSubscribe(['mentions' => ['Addr111']]);

        $sent = $this->decode($fake->sent[0]);
        self::assertSame('logsSubscribe', $sent['method']);
        self::assertSame(['mentions' => ['Addr111']], $sent['params'][0]);
    }

    #[Test]
    public function network_enum_exposes_pubsub_endpoints(): void
    {
        self::assertSame('wss://api.mainnet-beta.solana.com', Network::MAINNET->pubsubEndpoint());
        self::assertSame('wss://api.devnet.solana.com', Network::DEVNET->pubsubEndpoint());
        self::assertSame('wss://api.testnet.solana.com', Network::TESTNET->pubsubEndpoint());
    }
}

/**
 * Stand-in for `WebSocket\Client` that scripts canned responses instead of
 * opening a real connection. Extends `WebSocket\Client` so the production
 * type hint (`?Client`) is satisfied without `BypassFinals` gymnastics —
 * `Client` is non-final.
 *
 * The parent constructor accepts a URI and does *not* connect; the runtime
 * connect happens lazily inside `send()`/`receive()`. Overriding those two
 * methods skips that path entirely.
 */
final class FakeWebsocket extends Client
{
    /** @var list<string> */
    public array $sent = [];

    /**
     * @param list<string> $script
     */
    public function __construct(private array $script)
    {
        parent::__construct('ws://localhost:0');
    }

    /**
     * @return Text The same message instance, mirroring the parent contract.
     */
    public function send(Message $message): Text
    {
        if (! $message instanceof Text) {
            throw new RuntimeException('FakeWebsocket only handles Text frames.');
        }

        $this->sent[] = $message->getContent();

        return $message;
    }

    public function receive(): Text
    {
        if ($this->script === []) {
            throw new RuntimeException('scripted replies exhausted');
        }

        $next = array_shift($this->script);

        return new Text($next);
    }

    public function disconnect(): void {}
}
