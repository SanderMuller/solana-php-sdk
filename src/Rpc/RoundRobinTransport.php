<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

/**
 * Distributes successive requests across the wrapped transports in
 * order. On transient failure, falls through to the next transport
 * for the same request — so the caller always sees one logical
 * round-robin with built-in failover.
 *
 * @api
 */
final class RoundRobinTransport extends MultiEndpointTransport
{
    private int $cursor = 0;

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
        $count = count($this->transports);
        $start = $this->cursor;
        $this->cursor = ($this->cursor + 1) % $count;

        for ($i = 0; $i < $count; $i++) {
            yield $this->transports[($start + $i) % $count];
        }
    }
}
