<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Enum;

/**
 * Which stake-account authority is being re-assigned.
 *
 * @see https://docs.solanalabs.com/runtime/programs#stake-program
 */
enum StakeAuthorize: int
{
    case STAKER = 0;
    case WITHDRAWER = 1;
}
