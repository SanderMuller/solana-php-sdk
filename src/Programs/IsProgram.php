<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use Illuminate\Container\Container;
use LogicException;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;

/**
 * Shared shape for "program-like" classes (the `Program` interface).
 *
 * Exposes a virtual `$this->client` property backed by lazy container
 * resolution. We use `__get()` rather than 8.4 property hooks so the SDK
 * stays installable on PHP 8.3.
 *
 * @property-read SolanaRpcClient $client
 */
trait IsProgram
{
    private ?SolanaRpcClient $clientInstance = null;

    public function __get(string $name): mixed
    {
        if ($name === 'client') {
            return $this->clientInstance ??= Container::getInstance()->get(SolanaRpcClient::class);
        }

        throw new LogicException(sprintf('Undefined property %s::$%s.', static::class, $name));
    }

    public function __isset(string $name): bool
    {
        return $name === 'client';
    }

    /**
     * Internal hook for callers (e.g. tests) that want to inject an alternate
     * client without rebinding the container.
     */
    public function setClient(SolanaRpcClient $client): void
    {
        $this->clientInstance = $client;
    }
}
