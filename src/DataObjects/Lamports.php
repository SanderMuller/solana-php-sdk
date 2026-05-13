<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\DataObjects;

use Illuminate\Support\Number;

final readonly class Lamports
{
    public const int LAMPORTS_PER_SOL = 1_000_000_000;

    public function __construct(public int $lamports)
    {
        //
    }

    public static function fromSol(float $sol): self
    {
        return new self((int) ($sol * self::LAMPORTS_PER_SOL));
    }

    public function toSol(): float
    {
        return $this->lamports / self::LAMPORTS_PER_SOL;
    }

    public function toString(): string
    {
        $formatted = Number::format($this->toSol());

        return is_string($formatted) ? $formatted : '0';
    }
}
