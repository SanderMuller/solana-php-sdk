<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

interface Stringable extends \Stringable
{
    public function toString(): string;
}
