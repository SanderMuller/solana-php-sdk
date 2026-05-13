<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Enum\Encoding;
use SanderMuller\SolanaPhpSdk\Enum\StakeAuthorize;
use SanderMuller\SolanaPhpSdk\Enum\VoteAuthorize;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\StakeProgram;
use SanderMuller\SolanaPhpSdk\Programs\VoteProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class StakeVoteAuthorizeTest extends TestCase
{
    #[Test]
    public function stake_authorize_enum_matches_legacy_int_consts(): void
    {
        self::assertSame(StakeProgram::AUTHORIZE_STAKER, StakeAuthorize::STAKER->value); // @phpstan-ignore classConstant.deprecated
        self::assertSame(StakeProgram::AUTHORIZE_WITHDRAWER, StakeAuthorize::WITHDRAWER->value); // @phpstan-ignore classConstant.deprecated
    }

    #[Test]
    public function vote_authorize_enum_matches_legacy_int_consts(): void
    {
        self::assertSame(VoteProgram::AUTHORIZE_VOTER, VoteAuthorize::VOTER->value); // @phpstan-ignore classConstant.deprecated
        self::assertSame(VoteProgram::AUTHORIZE_WITHDRAWER, VoteAuthorize::WITHDRAWER->value); // @phpstan-ignore classConstant.deprecated
    }

    #[Test]
    public function stake_authorize_accepts_enum_or_int(): void
    {
        $stake = Keypair::generate()->getPublicKey();
        $current = Keypair::generate()->getPublicKey();
        $new = Keypair::generate()->getPublicKey();

        $byEnum = StakeProgram::authorize($stake, $current, $new, StakeAuthorize::WITHDRAWER);
        $byInt = StakeProgram::authorize($stake, $current, $new, 1);

        self::assertSame($byEnum->data->toArray(), $byInt->data->toArray());
    }

    #[Test]
    public function vote_authorize_accepts_enum_or_int(): void
    {
        $vote = Keypair::generate()->getPublicKey();
        $current = Keypair::generate()->getPublicKey();
        $new = Keypair::generate()->getPublicKey();

        $byEnum = VoteProgram::authorize($vote, $current, $new, VoteAuthorize::VOTER);
        $byInt = VoteProgram::authorize($vote, $current, $new, 0);

        self::assertSame($byEnum->data->toArray(), $byInt->data->toArray());
    }

    #[Test]
    public function encoding_enum_has_canonical_rpc_strings(): void
    {
        self::assertSame('base58', Encoding::BASE58->value);
        self::assertSame('base64', Encoding::BASE64->value);
        self::assertSame('base64+zstd', Encoding::BASE64_ZSTD->value);
        self::assertSame('jsonParsed', Encoding::JSON_PARSED->value);
    }
}
