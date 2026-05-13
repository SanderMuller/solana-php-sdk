<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

use InvalidArgumentException;
use SanderMuller\SolanaPhpSdk\Bootstrap;
use SensitiveParameter;

/**
 * Builds an {@see RpcTransport} from a config array. Shared between the
 * Laravel service provider, the standalone {@see Bootstrap},
 * and hand-rolled callers who want the same parsing rules.
 *
 * @api
 */
final class TransportFactory
{
    /**
     * @param array{
     *     mode?: string,
     *     urls?: list<string>,
     *     headers?: array<string, string>,
     *     timeout?: float|int,
     *     retry?: array{max_attempts?: int, base_delay_ms?: int, max_delay_ms?: int}|null,
     * }|null $config
     */
    public static function fromConfig(#[SensitiveParameter]
        ?array $config): ?RpcTransport
    {
        if ($config === null) {
            return null;
        }

        $urls = $config['urls'] ?? [];
        if (! is_array($urls) || $urls === []) {
            throw new InvalidArgumentException('Transport config requires a non-empty `urls` array.');
        }

        $headers = $config['headers'] ?? [];
        $timeout = (float) ($config['timeout'] ?? 30.0);
        $retry = $config['retry'] ?? null;

        $endpoints = [];
        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                throw new InvalidArgumentException('Each transport `urls` entry must be a non-empty string.');
            }

            $endpoint = new HttpTransport($url, $headers, $timeout);

            $endpoints[] = $retry === null
                ? $endpoint
                : new RetryTransport(
                    $endpoint,
                    maxAttempts: (int) ($retry['max_attempts'] ?? 3),
                    baseDelayMs: (int) ($retry['base_delay_ms'] ?? 100),
                    maxDelayMs: (int) ($retry['max_delay_ms'] ?? 2_000),
                );
        }

        $mode = $config['mode'] ?? 'fallback';

        return match ($mode) {
            'fallback' => count($endpoints) === 1 ? $endpoints[0] : new FallbackTransport($endpoints),
            'round_robin' => count($endpoints) === 1 ? $endpoints[0] : new RoundRobinTransport($endpoints),
            default => throw new InvalidArgumentException(
                "Unknown transport mode `{$mode}`. Expected `fallback` or `round_robin`.",
            ),
        };
    }
}
