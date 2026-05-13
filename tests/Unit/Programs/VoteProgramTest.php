<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Enum\VoteAuthorize;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\VoteProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class VoteProgramTest extends TestCase
{
    #[Test]
    public function authorize_packs_new_authority_and_role(): void
    {
        $vote = Keypair::generate()->getPublicKey();
        $current = Keypair::generate()->getPublicKey();
        $new = Keypair::generate()->getPublicKey();

        $ix = VoteProgram::authorize($vote, $current, $new, VoteAuthorize::WITHDRAWER);
        $data = $ix->data->toArray();

        self::assertCount(4 + 32 + 4, $data);
        self::assertSame([1, 0, 0, 0], array_slice($data, 0, 4));
        self::assertSame($new->toBytes()[0], $data[4]);
        self::assertSame([1, 0, 0, 0], array_slice($data, 4 + 32, 4));

        self::assertCount(3, $ix->keys);
        self::assertSame(VoteProgram::SYSVAR_CLOCK, $ix->keys[1]->getPublicKey()->toBase58());
        self::assertTrue($ix->keys[2]->isSigner);
    }

    #[Test]
    public function withdraw_packs_lamports(): void
    {
        $vote = Keypair::generate()->getPublicKey();
        $recipient = Keypair::generate()->getPublicKey();
        $auth = Keypair::generate()->getPublicKey();

        $ix = VoteProgram::withdraw($vote, $recipient, $auth, 5_000_000_000);
        $data = $ix->data->toArray();

        self::assertSame([3, 0, 0, 0], array_slice($data, 0, 4));
        // 5_000_000_000 = 0x0000_0001_2A05_F200
        self::assertSame([0x00, 0xF2, 0x05, 0x2A, 0x01, 0, 0, 0], array_slice($data, 4, 8));

        self::assertCount(3, $ix->keys);
        self::assertTrue($ix->keys[0]->isWritable);
        self::assertTrue($ix->keys[1]->isWritable);
        self::assertTrue($ix->keys[2]->isSigner);
    }

    #[Test]
    public function update_validator_identity_requires_two_signers(): void
    {
        $vote = Keypair::generate()->getPublicKey();
        $newId = Keypair::generate()->getPublicKey();
        $auth = Keypair::generate()->getPublicKey();

        $ix = VoteProgram::updateValidatorIdentity($vote, $newId, $auth);

        self::assertSame([4, 0, 0, 0], $ix->data->toArray());
        self::assertCount(3, $ix->keys);
        self::assertTrue($ix->keys[1]->isSigner);
        self::assertTrue($ix->keys[2]->isSigner);
    }

    #[Test]
    public function update_commission_appends_u8(): void
    {
        $vote = Keypair::generate()->getPublicKey();
        $auth = Keypair::generate()->getPublicKey();

        $ix = VoteProgram::updateCommission($vote, $auth, 7);
        $data = $ix->data->toArray();

        self::assertSame([5, 0, 0, 0, 7], $data);
        self::assertCount(2, $ix->keys);
        self::assertTrue($ix->keys[1]->isSigner);
    }
}
