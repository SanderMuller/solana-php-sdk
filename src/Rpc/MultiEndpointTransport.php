<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

use InvalidArgumentException;

/**
 * Shared base for transports that wrap multiple inner transports and
 * try them in turn on transient failure. Subclasses implement
 * {@see order()} to decide the per-call iteration order — fallback
 * returns the list as-is, round-robin advances a cursor.
 *
 * @api
 */
abstract class MultiEndpointTransport implements RpcTransport
{
    /** @var list<RpcTransport> */
    protected readonly array $transports;

    /**
     * @param iterable<RpcTransport> $transports
     */
    public function __construct(iterable $transports)
    {
        $list = [];
        foreach ($transports as $t) {
            $list[] = $t;
        }

        if ($list === []) {
            throw new InvalidArgumentException(static::class . ' requires at least one inner transport.');
        }

        $this->transports = $list;
    }

    public function send(array $payload): array
    {
        $lastError = new RpcTransportException(static::class . ' exhausted', transient: false);

        foreach ($this->order() as $transport) {
            try {
                return $transport->send($payload);
            } catch (RpcTransportException $e) {
                $lastError = $e;

                if (! $e->transient) {
                    throw $e;
                }
            }
        }

        throw $lastError;
    }

    /**
     * @return iterable<RpcTransport>
     */
    abstract protected function order(): iterable;
}
