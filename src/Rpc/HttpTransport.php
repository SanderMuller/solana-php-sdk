<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Single-endpoint HTTP transport backed by Laravel's HTTP client factory.
 *
 * @api
 */
final readonly class HttpTransport implements RpcTransport
{
    private Factory $http;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $endpoint,
        private array $headers = [],
        private float $timeoutSeconds = 30.0,
        ?Factory $http = null,
    ) {
        $this->http = $http ?? new Factory();
    }

    public function send(array $payload): array
    {
        try {
            $response = $this->http
                ->createPendingRequest()
                ->withHeaders($this->headers)
                ->asJson()
                ->acceptJson()
                ->timeout((int) ceil($this->timeoutSeconds))
                ->post($this->endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new RpcTransportException(
                'RPC transport connection error: ' . $e->getMessage(),
                transient: true,
                previous: $e,
            );
        } catch (Throwable $e) {
            throw new RpcTransportException(
                'RPC transport error: ' . $e->getMessage(),
                transient: false,
                previous: $e,
            );
        }

        $status = $response->status();

        if ($status >= 400) {
            $transient = $status === 408 || $status === 429 || $status >= 500;
            $cause = null;

            try {
                $response->throw();
            } catch (RequestException $e) {
                $cause = $e;
            }

            throw new RpcTransportException(
                "RPC transport HTTP {$status} from {$this->endpoint}",
                transient: $transient,
                httpStatus: $status,
                previous: $cause,
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RpcTransportException(
                'RPC transport returned non-JSON body',
                transient: false,
                httpStatus: $status,
            );
        }

        /** @var array<string, mixed> $body */
        return $body;
    }
}
