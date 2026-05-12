<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SplToken\Instructions;

/**
 * SPL Token authority types accepted by SetAuthority.
 *
 * @see https://github.com/solana-labs/solana-program-library/blob/master/token/program/src/instruction.rs
 */
final class AuthorityType
{
    public const int MINT_TOKENS = 0;

    public const int FREEZE_ACCOUNT = 1;

    public const int ACCOUNT_OWNER = 2;

    public const int CLOSE_ACCOUNT = 3;
}
