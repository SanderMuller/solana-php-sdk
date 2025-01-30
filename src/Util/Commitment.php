<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;

final readonly class Commitment implements Stringable
{
    private const string FINALIZED = 'finalized';

    private const string CONFIRMED = 'confirmed';

    private const string PROCESSED = 'processed';

    public function __construct(private string $commitmentLevel)
    {
        if (! in_array($this->commitmentLevel, [
            self::FINALIZED,
            self::CONFIRMED,
            self::PROCESSED,
        ])) {
            throw new InputValidationException('Invalid commitment level.');
        }
    }

    public static function finalized(): Commitment
    {
        return new self(self::FINALIZED);
    }

    public static function confirmed(): Commitment
    {
        return new self(self::CONFIRMED);
    }

    public static function processed(): Commitment
    {
        return new self(self::PROCESSED);
    }

    public function toString(): string
    {
        return $this->commitmentLevel;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
