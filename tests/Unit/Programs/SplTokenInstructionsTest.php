<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions\AuthorityType;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions\TokenInstruction;
use SanderMuller\SolanaPhpSdk\Programs\SplTokenProgram;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class SplTokenInstructionsTest extends TestCase
{
    private SplTokenProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->program = new SplTokenProgram();
    }

    #[Test]
    public function transfer_layout(): void
    {
        $source = Keypair::generate()->getPublicKey();
        $dest = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $ix = $this->program->createTransferInstruction($source, $dest, $owner, 100_000);
        $bytes = $ix->data->toArray();

        self::assertSame(TokenInstruction::Transfer, $bytes[0]);
        // u64 LE = 100000 -> A0 86 01 00 00 00 00 00
        self::assertSame([0xA0, 0x86, 0x01, 0x00, 0x00, 0x00, 0x00, 0x00], array_slice($bytes, 1));
        self::assertCount(3, $ix->keys);
        self::assertTrue($ix->keys[2]->isSigner);
    }

    #[Test]
    public function transfer_checked_includes_mint_and_decimals(): void
    {
        $source = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();
        $dest = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $ix = $this->program->createTransferCheckedInstruction($source, $mint, $dest, $owner, 1, 9);
        $bytes = $ix->data->toArray();

        self::assertSame(TokenInstruction::TransferChecked, $bytes[0]);
        self::assertSame(9, $bytes[9]);
        self::assertCount(4, $ix->keys);
        self::assertFalse($ix->keys[1]->isWritable); // mint readonly
    }

    #[Test]
    public function mint_to_keys_and_amount(): void
    {
        $mint = Keypair::generate()->getPublicKey();
        $dest = Keypair::generate()->getPublicKey();
        $authority = Keypair::generate()->getPublicKey();

        $ix = $this->program->createMintToInstruction($mint, $dest, $authority, 500);
        $bytes = $ix->data->toArray();

        self::assertSame(TokenInstruction::MintTo, $bytes[0]);
        $unpacked = unpack('P', pack('C*', ...array_slice($bytes, 1, 8)));
        self::assertIsArray($unpacked);
        self::assertSame(500, $unpacked[1]);
        self::assertTrue($ix->keys[0]->isWritable); // mint writable
        self::assertTrue($ix->keys[1]->isWritable); // dest writable
        self::assertTrue($ix->keys[2]->isSigner);   // authority
    }

    #[Test]
    public function burn_writes_account_and_mint(): void
    {
        $account = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $ix = $this->program->createBurnInstruction($account, $mint, $owner, 7);
        self::assertSame(TokenInstruction::Burn, $ix->data->toArray()[0]);
        self::assertTrue($ix->keys[0]->isWritable);
        self::assertTrue($ix->keys[1]->isWritable);
        self::assertTrue($ix->keys[2]->isSigner);
    }

    #[Test]
    public function approve_revoke_close(): void
    {
        $source = Keypair::generate()->getPublicKey();
        $delegate = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();
        $dest = Keypair::generate()->getPublicKey();

        $approve = $this->program->createApproveInstruction($source, $delegate, $owner, 42);
        self::assertSame(TokenInstruction::Approve, $approve->data->toArray()[0]);

        $revoke = $this->program->createRevokeInstruction($source, $owner);
        self::assertSame([TokenInstruction::Revoke], $revoke->data->toArray());
        self::assertCount(2, $revoke->keys);

        $close = $this->program->createCloseAccountInstruction($source, $dest, $owner);
        self::assertSame([TokenInstruction::CloseAccount], $close->data->toArray());
    }

    #[Test]
    public function freeze_thaw(): void
    {
        $account = Keypair::generate()->getPublicKey();
        $mint = Keypair::generate()->getPublicKey();
        $authority = Keypair::generate()->getPublicKey();

        $freeze = $this->program->createFreezeAccountInstruction($account, $mint, $authority);
        self::assertSame([TokenInstruction::FreezeAccount], $freeze->data->toArray());

        $thaw = $this->program->createThawAccountInstruction($account, $mint, $authority);
        self::assertSame([TokenInstruction::ThawAccount], $thaw->data->toArray());
    }

    #[Test]
    public function set_authority_with_new_key(): void
    {
        $account = Keypair::generate()->getPublicKey();
        $current = Keypair::generate()->getPublicKey();
        $new = Keypair::generate()->getPublicKey();

        $ix = $this->program->createSetAuthorityInstruction(
            account: $account,
            currentAuthority: $current,
            authorityType: AuthorityType::ACCOUNT_OWNER,
            newAuthority: $new,
        );

        $bytes = $ix->data->toArray();
        self::assertSame(TokenInstruction::SetAuthority, $bytes[0]);
        self::assertSame(AuthorityType::ACCOUNT_OWNER, $bytes[1]);
        self::assertSame(1, $bytes[2]); // Option<PublicKey> tag = Some
        self::assertSame($new->toBytes(), array_slice($bytes, 3));
    }

    #[Test]
    public function set_authority_revoke_with_none(): void
    {
        $account = Keypair::generate()->getPublicKey();
        $current = Keypair::generate()->getPublicKey();

        $ix = $this->program->createSetAuthorityInstruction(
            account: $account,
            currentAuthority: $current,
            authorityType: AuthorityType::CLOSE_ACCOUNT,
            newAuthority: null,
        );

        self::assertSame(
            [TokenInstruction::SetAuthority, AuthorityType::CLOSE_ACCOUNT, 0],
            $ix->data->toArray()
        );
    }

    #[Test]
    public function initialize_mint_layout(): void
    {
        $mint = Keypair::generate()->getPublicKey();
        $mintAuth = Keypair::generate()->getPublicKey();
        $freezeAuth = Keypair::generate()->getPublicKey();

        $ix = $this->program->createInitializeMintInstruction($mint, 6, $mintAuth, $freezeAuth);
        $bytes = $ix->data->toArray();

        self::assertSame(TokenInstruction::InitializeMint2, $bytes[0]);
        self::assertSame(6, $bytes[1]);
        self::assertSame($mintAuth->toBytes(), array_slice($bytes, 2, 32));
        self::assertSame(1, $bytes[34]);
        self::assertSame($freezeAuth->toBytes(), array_slice($bytes, 35));
    }

    #[Test]
    public function multisig_expands_authority(): void
    {
        $source = Keypair::generate()->getPublicKey();
        $dest = Keypair::generate()->getPublicKey();
        $authority = Keypair::generate()->getPublicKey();
        $signer1 = Keypair::generate()->getPublicKey();
        $signer2 = Keypair::generate()->getPublicKey();

        $ix = $this->program->createTransferInstruction($source, $dest, $authority, 1, [$signer1, $signer2]);

        self::assertCount(5, $ix->keys);
        self::assertFalse($ix->keys[2]->isSigner);
        self::assertTrue($ix->keys[3]->isSigner);
        self::assertTrue($ix->keys[4]->isSigner);
    }
}
