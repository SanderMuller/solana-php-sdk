<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Rpc;

use Closure;
use Illuminate\Support\Sleep;
use InvalidArgumentException;

/**
 * Retries the wrapped transport on transient failures with exponential
 * backoff + full jitter. Non-transient failures bubble up immediately.
 *
 * The default sleeper uses {@see Sleep::usleep()} so calls compress to
 * zero under {@see Sleep::fake()} in tests.
 *
 * @api
 */
final readonly class RetryTransport implements RpcTransport
{
    /**
     * Hard cap for the per-attempt delay in ms. The constructor clamps
     * `maxDelayMs` to this value so the exponential backoff arithmetic
     * stays inside PHP's int range when multiplied by 1_000 to reach
     * microseconds.
     */
    public const int MAX_DELAY_HARD_CAP_MS = 60_000;

    private int $maxDelayMs;

    /** @var Closure(int): void */
    private Closure $sleeper;

    /**
     * @param (Closure(int): void)|null $sleeper hook for tests; receives microseconds.
     */
    public function __construct(
        private RpcTransport $inner,
        private int $maxAttempts = 3,
        private int $baseDelayMs = 100,
        int $maxDelayMs = 2_000,
        ?Closure $sleeper = null,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be >= 1.');
        }

        if ($baseDelayMs < 0) {
            throw new InvalidArgumentException('baseDelayMs must be non-negative.');
        }

        if ($maxDelayMs < $baseDelayMs) {
            throw new InvalidArgumentException('maxDelayMs must be >= baseDelayMs.');
        }

        $this->maxDelayMs = min($maxDelayMs, self::MAX_DELAY_HARD_CAP_MS);
        $this->sleeper = $sleeper ?? static function (int $us): void {
            Sleep::usleep($us);
        };
    }

    public function send(array $payload): array
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->inner->send($payload);
            } catch (RpcTransportException $e) {
                $attempt++;

                if (! $e->transient || $attempt >= $this->maxAttempts) {
                    throw $e;
                }

                ($this->sleeper)($this->backoffMicroseconds($attempt));
            }
        }
    }

    private function backoffMicroseconds(int $attempt): int
    {
        // Bound the exponent so `2 ** N` cannot float-overflow even with
        // pathologically large baseDelayMs. log2(MAX_DELAY_HARD_CAP_MS)
        // saturates at ~16; any larger exponent would just be clamped to
        // maxDelayMs anyway.
        $boundedAttempt = min($attempt, 24);
        $exponential = $this->baseDelayMs * (2 ** ($boundedAttempt - 1));
        $cappedMs = min($exponential, $this->maxDelayMs);

        if ($cappedMs <= 0) {
            return 0;
        }

        return random_int(0, $cappedMs * 1_000);
    }
}
