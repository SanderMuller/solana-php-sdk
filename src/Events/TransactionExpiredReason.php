<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Events;

/**
 * Discriminates {@see TransactionExpired} causes so listeners can
 * exhaustively `match` instead of comparing strings.
 *
 * @api
 */
enum TransactionExpiredReason: string
{
    case Blockhash = 'expired_blockhash';
    case Timeout = 'timeout';
    case FailedOnChain = 'failed_on_chain';
}
