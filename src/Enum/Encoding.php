<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Enum;

/**
 * Solana RPC account / transaction encodings.
 *
 * `base58` is legacy and capped at 129 bytes — avoid it on accounts.
 * `base64` is the bulk-binary default. `base64+zstd` compresses; client must
 * decompress before parsing. `jsonParsed` asks the node to decode known
 * account states (SPL Token, Stake, Vote, Nonce) into structured JSON and
 * falls back to base64 when no parser exists.
 *
 * @see https://solana.com/docs/rpc#parsed-responses
 */
enum Encoding: string
{
    case BASE58 = 'base58';
    case BASE64 = 'base64';
    case BASE64_ZSTD = 'base64+zstd';
    case JSON_PARSED = 'jsonParsed';
}
