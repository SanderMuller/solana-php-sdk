<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Enum;

/**
 * Which vote-account authority is being re-assigned.
 *
 * @see https://docs.solanalabs.com/runtime/programs#vote-program
 */
enum VoteAuthorize: int
{
    case VOTER = 0;
    case WITHDRAWER = 1;
}
