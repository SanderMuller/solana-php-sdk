<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Enum\StakeAuthorize;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\StakeProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class StakeProgramTest extends TestCase
{
    #[Test]
    public function initialize_packs_authorized_and_lockup(): void
    {
        $stake = Keypair::generate()->getPublicKey();
        $staker = Keypair::generate()->getPublicKey();
        $withdrawer = Keypair::generate()->getPublicKey();

        $ix = StakeProgram::initialize($stake, $staker, $withdrawer);
        $data = $ix->data->toArray();

        // 4 (discriminator) + 32 (staker) + 32 (withdrawer) + 8 (i64) + 8 (u64) + 32 (custodian) = 116
        self::assertCount(116, $data);
        self::assertSame([0, 0, 0, 0], array_slice($data, 0, 4));
        self::assertSame($staker->toBytes()[0], $data[4]);
        self::assertSame($withdrawer->toBytes()[31], $data[4 + 32 + 31]);
        // Lockup zero-valued + default custodian
        self::assertSame(array_fill(0, 48, 0), array_slice($data, 4 + 64, 48));

        self::assertCount(2, $ix->keys);
        self::assertSame(StakeProgram::SYSVAR_RENT, $ix->keys[1]->getPublicKey()->toBase58());
    }

    #[Test]
    public function authorize_packs_new_authority_and_role(): void
    {
        $stake = Keypair::generate()->getPublicKey();
        $current = Keypair::generate()->getPublicKey();
        $new = Keypair::generate()->getPublicKey();

        $ix = StakeProgram::authorize($stake, $current, $new, StakeAuthorize::WITHDRAWER);
        $data = $ix->data->toArray();

        self::assertCount(4 + 32 + 4, $data);
        self::assertSame([1, 0, 0, 0], array_slice($data, 0, 4));
        self::assertSame($new->toBytes()[0], $data[4]);
        // u32 LE = 1 (Withdrawer)
        self::assertSame([1, 0, 0, 0], array_slice($data, 4 + 32, 4));

        self::assertCount(3, $ix->keys);
        self::assertTrue($ix->keys[2]->isSigner);
    }

    #[Test]
    public function authorize_appends_custodian_when_provided(): void
    {
        $stake = Keypair::generate()->getPublicKey();
        $current = Keypair::generate()->getPublicKey();
        $new = Keypair::generate()->getPublicKey();
        $custodian = Keypair::generate()->getPublicKey();

        $ix = StakeProgram::authorize($stake, $current, $new, StakeAuthorize::STAKER, $custodian);

        self::assertCount(4, $ix->keys);
        self::assertTrue($ix->keys[3]->isSigner);
        self::assertSame($custodian->toBase58(), $ix->keys[3]->getPublicKey()->toBase58());
    }

    #[Test]
    public function delegate_stake_uses_5_sysvar_accounts_plus_authority(): void
    {
        $stake = Keypair::generate()->getPublicKey();
        $vote = Keypair::generate()->getPublicKey();
        $auth = Keypair::generate()->getPublicKey();

        $ix = StakeProgram::delegateStake($stake, $vote, $auth);

        self::assertSame([2, 0, 0, 0], $ix->data->toArray());
        self::assertCount(6, $ix->keys);
        self::assertSame(StakeProgram::STAKE_CONFIG, $ix->keys[4]->getPublicKey()->toBase58());
        self::assertTrue($ix->keys[5]->isSigner);
    }

    #[Test]
    public function split_packs_lamports(): void
    {
        $a = Keypair::generate()->getPublicKey();
        $b = Keypair::generate()->getPublicKey();
        $auth = Keypair::generate()->getPublicKey();

        $ix = StakeProgram::split($a, $b, $auth, 100_000_000);
        $data = $ix->data->toArray();

        self::assertSame([3, 0, 0, 0], array_slice($data, 0, 4));
        // 100_000_000 = 0x05F5E100
        self::assertSame([0x00, 0xE1, 0xF5, 0x05, 0, 0, 0, 0], array_slice($data, 4, 8));
    }

    #[Test]
    public function withdraw_packs_lamports_and_optional_custodian(): void
    {
        $stake = Keypair::generate()->getPublicKey();
        $recipient = Keypair::generate()->getPublicKey();
        $auth = Keypair::generate()->getPublicKey();
        $custodian = Keypair::generate()->getPublicKey();

        $bare = StakeProgram::withdraw($stake, $recipient, $auth, 1);
        $withCustodian = StakeProgram::withdraw($stake, $recipient, $auth, 1, $custodian);

        self::assertCount(5, $bare->keys);
        self::assertCount(6, $withCustodian->keys);
        self::assertTrue($withCustodian->keys[5]->isSigner);
    }

    #[Test]
    public function deactivate_and_merge_emit_correct_discriminators(): void
    {
        $stake = Keypair::generate()->getPublicKey();
        $other = Keypair::generate()->getPublicKey();
        $auth = Keypair::generate()->getPublicKey();

        self::assertSame([5, 0, 0, 0], StakeProgram::deactivate($stake, $auth)->data->toArray());
        self::assertSame([7, 0, 0, 0], StakeProgram::merge($stake, $other, $auth)->data->toArray());
    }
}
