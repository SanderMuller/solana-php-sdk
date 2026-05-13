<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

final class ConfirmOptions
{
    public function __construct(
        public bool $skipPreflight = false,
        public Commitment $commitment = new Commitment('confirmed'),
        public Commitment $preflightCommitment = new Commitment('confirmed'),
        public int $maxRetries = 0,
        public int $minContextSlot = 0,
    ) {}
}
