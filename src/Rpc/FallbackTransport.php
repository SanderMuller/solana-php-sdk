<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

/**
 * Tries each wrapped transport in order, advancing only on transient
 * failures (5xx, 429, connection errors). Non-transient failures
 * (4xx other than 429) abort immediately.
 *
 * @api
 */
final class FallbackTransport extends MultiEndpointTransport
{
    /**
     * @param iterable<string> $urls
     * @param array<string, string> $headers shared across all endpoints
     */
    public static function fromUrls(iterable $urls, array $headers = [], float $timeoutSeconds = 30.0): self
    {
        $transports = [];
        foreach ($urls as $url) {
            $transports[] = new HttpTransport($url, $headers, $timeoutSeconds);
        }

        return new self($transports);
    }

    protected function order(): iterable
    {
        return $this->transports;
    }
}
