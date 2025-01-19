<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Util;

/**
 * @property bool $skipPreflight
 * @property Commitment $commitment
 * @property Commitment $preflightCommitment
 * @property int $maxRetries
 * @property int $minContextSlot
 */
final class ConfirmOptions
{
    /**
     * @param Commitment|null $commitment
     * @param Commitment|null $preflightCommitment
     */
    public function __construct(public bool $skipPreflight = false, public Commitment $commitment = new Commitment('confirmed'), public Commitment $preflightCommitment = new Commitment('confirmed'), public int $maxRetries = 0, public int $minContextSlot = 0) {}
}
