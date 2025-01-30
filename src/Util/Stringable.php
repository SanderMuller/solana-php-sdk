<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

interface Stringable extends \Stringable
{
    public function toString(): string;
}
