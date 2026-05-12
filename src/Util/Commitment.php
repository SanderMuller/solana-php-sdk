<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;

final readonly class Commitment implements Stringable
{
    public const string FINALIZED = 'finalized';

    public const string CONFIRMED = 'confirmed';

    public const string PROCESSED = 'processed';

    public function __construct(public string $commitmentLevel)
    {
        if (! in_array($this->commitmentLevel, [
            self::FINALIZED,
            self::CONFIRMED,
            self::PROCESSED,
        ],
            true)) {
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
