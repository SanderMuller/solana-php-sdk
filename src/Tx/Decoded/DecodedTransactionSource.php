<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * Discriminates between {@see DecodedTransaction} construction paths
 * so consumers never have to guess whether a null field means "not yet
 * known" or "RPC returned no data".
 *
 * @api
 */
enum DecodedTransactionSource: string
{
    case Rpc = 'rpc';
    case Outbound = 'outbound';
}
