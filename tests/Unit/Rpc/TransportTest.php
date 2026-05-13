<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Rpc;

use Illuminate\Support\Sleep;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\SolanaPhpSdk\Rpc\FallbackTransport;
use SanderMuller\SolanaPhpSdk\Rpc\RetryTransport;
use SanderMuller\SolanaPhpSdk\Rpc\RoundRobinTransport;
use SanderMuller\SolanaPhpSdk\Rpc\RpcTransport;
use SanderMuller\SolanaPhpSdk\Rpc\RpcTransportException;
use SanderMuller\SolanaPhpSdk\Rpc\TransportFactory;

/** @internal */
final class TransportTest extends TestCase
{
    #[Test]
    public function fallback_advances_on_transient_failure(): void
    {
        $primary = $this->throwingTransport(transient: true);
        $secondary = $this->echoingTransport(['result' => 'ok']);

        $transport = new FallbackTransport([$primary, $secondary]);

        $body = $transport->send($this->payload());

        self::assertSame('ok', $body['result']);
    }

    #[Test]
    public function fallback_aborts_on_non_transient_failure(): void
    {
        $primary = $this->throwingTransport(transient: false);
        $secondary = $this->echoingTransport(['result' => 'unreachable']);

        $this->expectException(RpcTransportException::class);

        (new FallbackTransport([$primary, $secondary]))->send($this->payload());
    }

    #[Test]
    public function round_robin_cycles_endpoints(): void
    {
        $seen = ['a' => 0, 'b' => 0];

        $a = $this->countingTransport($seen, 'a');
        $b = $this->countingTransport($seen, 'b');

        $transport = new RoundRobinTransport([$a, $b]);

        for ($i = 0; $i < 4; $i++) {
            $transport->send($this->payload());
        }

        self::assertSame(2, $seen['a']);
        self::assertSame(2, $seen['b']);
    }

    #[Test]
    public function retry_succeeds_after_transient_failures(): void
    {
        Sleep::fake();

        $attempts = 0;
        $inner = new class ($attempts) implements RpcTransport {
            public function __construct(public int &$attempts) {}

            /**
             * @return array<string, string>
             */
            public function send(array $payload): array
            {
                $this->attempts++;
                if ($this->attempts < 3) {
                    throw new RpcTransportException('boom', transient: true);
                }

                return ['jsonrpc' => '2.0', 'id' => $payload['id'], 'result' => 'won'];
            }
        };

        $transport = new RetryTransport($inner, maxAttempts: 5, baseDelayMs: 1);

        $body = $transport->send($this->payload());

        self::assertSame('won', $body['result']);
        self::assertSame(3, $attempts);
    }

    #[Test]
    public function retry_bubbles_non_transient_immediately(): void
    {
        Sleep::fake();

        $attempts = 0;
        $inner = new class ($attempts) implements RpcTransport {
            public function __construct(public int &$attempts) {}

            public function send(array $payload): array
            {
                $this->attempts++;
                throw new RpcTransportException('nope', transient: false);
            }
        };

        $transport = new RetryTransport($inner, maxAttempts: 5);

        try {
            $transport->send($this->payload());
            self::fail('expected exception');
        } catch (RpcTransportException $rpcTransportException) {
            self::assertSame(1, $attempts);
            self::assertFalse($rpcTransportException->transient);
        }
    }

    #[Test]
    public function retry_transport_validates_constructor_args(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RetryTransport($this->throwingTransport(transient: true), maxAttempts: 0);
    }

    #[Test]
    public function retry_transport_rejects_max_delay_below_base(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RetryTransport(
            $this->throwingTransport(transient: true),
            maxAttempts: 3,
            baseDelayMs: 1_000,
            maxDelayMs: 500,
        );
    }

    #[Test]
    public function retry_transport_clamps_huge_delay_to_hard_cap(): void
    {
        Sleep::fake();

        // baseDelayMs would overflow on the 30th attempt without clamping;
        // we want construction to succeed and any send loop to terminate.
        $transport = new RetryTransport(
            $this->throwingTransport(transient: false),
            maxAttempts: 2,
            baseDelayMs: 0,
            maxDelayMs: RetryTransport::MAX_DELAY_HARD_CAP_MS * 10,
        );

        $this->expectException(RpcTransportException::class);
        $transport->send($this->payload());
    }

    #[Test]
    public function transport_factory_builds_fallback_with_retry(): void
    {
        $transport = TransportFactory::fromConfig([
            'mode' => 'fallback',
            'urls' => ['https://a.example/', 'https://b.example/'],
            'retry' => ['max_attempts' => 2, 'base_delay_ms' => 1],
        ]);

        self::assertInstanceOf(FallbackTransport::class, $transport);
    }

    #[Test]
    public function transport_factory_returns_null_for_null_config(): void
    {
        self::assertNull(TransportFactory::fromConfig(null));
    }

    /**
     * @return array{jsonrpc: string, id: string, method: string, params: array<mixed>}
     */
    private function payload(): array
    {
        return ['jsonrpc' => '2.0', 'id' => 'test-id', 'method' => 'getSlot', 'params' => []];
    }

    private function throwingTransport(bool $transient): RpcTransport
    {
        return new readonly class ($transient) implements RpcTransport {
            public function __construct(private bool $transient) {}

            public function send(array $payload): array
            {
                throw new RpcTransportException('boom', transient: $this->transient);
            }
        };
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function echoingTransport(array $extra): RpcTransport
    {
        return new readonly class ($extra) implements RpcTransport {
            /** @param array<string, mixed> $extra */
            public function __construct(private array $extra) {}

            public function send(array $payload): array
            {
                return ['jsonrpc' => '2.0', 'id' => $payload['id']] + $this->extra;
            }
        };
    }

    /**
     * @param array<string, int> $sink
     */
    private function countingTransport(array &$sink, string $label): RpcTransport
    {
        return new class ($sink, $label) implements RpcTransport {
            /** @param array<string, int> $sink */
            public function __construct(public array &$sink, public string $label) {}

            /**
             * @return array<string, string|null>
             */
            public function send(array $payload): array
            {
                $this->sink[$this->label]++;

                return ['jsonrpc' => '2.0', 'id' => $payload['id'], 'result' => null];
            }
        };
    }
}
