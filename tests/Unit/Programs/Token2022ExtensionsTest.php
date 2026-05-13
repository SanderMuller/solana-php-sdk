<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Programs;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions\TokenInstruction;
use SanderMuller\SolanaPhpSdk\Programs\Token2022Program;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class Token2022ExtensionsTest extends TestCase
{
    #[Test]
    public function initialize_immutable_owner_emits_single_byte_discriminator(): void
    {
        $program = new Token2022Program();
        $ix = $program->createInitializeImmutableOwnerInstruction(Keypair::generate()->getPublicKey());

        self::assertSame(Token2022Program::TOKEN_PROGRAM_ID, $ix->programId->toBase58());
        self::assertSame([TokenInstruction::InitializeImmutableOwner], $ix->data->toArray());
        self::assertCount(1, $ix->keys);
    }

    #[Test]
    public function initialize_non_transferable_mint_emits_single_byte_discriminator(): void
    {
        $program = new Token2022Program();
        $ix = $program->createInitializeNonTransferableMintInstruction(Keypair::generate()->getPublicKey());

        self::assertSame([TokenInstruction::InitializeNonTransferableMint], $ix->data->toArray());
    }

    #[Test]
    public function initialize_permanent_delegate_packs_pubkey(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();
        $delegate = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializePermanentDelegateInstruction($mint, $delegate);
        $data = $ix->data->toArray();

        self::assertCount(33, $data);
        self::assertSame(TokenInstruction::InitializePermanentDelegate, $data[0]);
        self::assertSame($delegate->toBytes()[0], $data[1]);
        self::assertSame($delegate->toBytes()[31], $data[32]);
    }

    #[Test]
    public function initialize_mint_close_authority_encodes_coption_some(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();
        $authority = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeMintCloseAuthorityInstruction($mint, $authority);
        $data = $ix->data->toArray();

        self::assertCount(34, $data);
        self::assertSame(TokenInstruction::InitializeMintCloseAuthority, $data[0]);
        self::assertSame(1, $data[1]);                          // COption::Some tag
        self::assertSame($authority->toBytes()[0], $data[2]);
    }

    #[Test]
    public function transfer_fee_config_encodes_full_layout(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();
        $configAuth = Keypair::generate()->getPublicKey();
        $withdrawAuth = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeTransferFeeConfigInstruction(
            mint: $mint,
            transferFeeConfigAuthority: $configAuth,
            withdrawWithheldAuthority: $withdrawAuth,
            transferFeeBasisPoints: 250,
            maximumFee: 1_000_000,
        );
        $data = $ix->data->toArray();

        // 1 (ins) + 1 (sub) + (1 + 32) + (1 + 32) + 2 (u16) + 8 (u64) = 78
        self::assertCount(78, $data);
        self::assertSame(TokenInstruction::TransferFeeExtension, $data[0]);
        self::assertSame(0, $data[1]);                              // sub-discriminator 0
        self::assertSame(1, $data[2]);                              // COption::Some for fee config authority
        self::assertSame($configAuth->toBytes()[0], $data[3]);
        self::assertSame(1, $data[35]);                             // COption::Some for withdraw authority
        self::assertSame($withdrawAuth->toBytes()[0], $data[36]);
        // u16 LE 250 = 0xFA 0x00
        self::assertSame(0xFA, $data[68]);
        self::assertSame(0x00, $data[69]);
        // u64 LE 1_000_000 = 0x40 0x42 0x0F 00 00 00 00 00
        self::assertSame(0x40, $data[70]);
        self::assertSame(0x42, $data[71]);
        self::assertSame(0x0F, $data[72]);
    }

    #[Test]
    public function transfer_fee_config_handles_none_authorities(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeTransferFeeConfigInstruction(
            mint: $mint,
            transferFeeConfigAuthority: null,
            withdrawWithheldAuthority: null,
            transferFeeBasisPoints: 0,
            maximumFee: 0,
        );
        $data = $ix->data->toArray();

        // 1 + 1 + 1 + 1 + 2 + 8 = 14
        self::assertCount(14, $data);
        self::assertSame(0, $data[2]);                              // COption::None
        self::assertSame(0, $data[3]);                              // COption::None
    }

    #[Test]
    public function memo_transfer_toggle_emits_enable_zero_or_disable_one(): void
    {
        // `RequiredMemoTransfersInstruction` exposes only Enable=0 + Disable=1.
        // Enable both initializes the extension AND turns the requirement on,
        // so there's no separate Initialize discriminator to call.
        $program = new Token2022Program();
        $tokenAccount = Keypair::generate()->getPublicKey();
        $owner = Keypair::generate()->getPublicKey();

        $enable = $program->createMemoTransferToggleInstruction($tokenAccount, $owner, enable: true);
        $disable = $program->createMemoTransferToggleInstruction($tokenAccount, $owner, enable: false);

        self::assertSame([TokenInstruction::MemoTransferExtension, 0], $enable->data->toArray());
        self::assertSame([TokenInstruction::MemoTransferExtension, 1], $disable->data->toArray());
        self::assertCount(2, $enable->keys);
        self::assertTrue($enable->keys[1]->isSigner);
    }

    #[Test]
    public function transfer_hook_init_uses_optional_non_zero_pubkey_layout(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();
        $authority = Keypair::generate()->getPublicKey();
        $hook = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeTransferHookInstruction($mint, $authority, $hook);
        $data = $ix->data->toArray();

        // 1 (ins) + 1 (sub) + 32 (authority) + 32 (hook) = 66
        self::assertCount(66, $data);
        self::assertSame(TokenInstruction::TransferHookExtension, $data[0]);
        self::assertSame(0, $data[1]);                                  // sub-discriminator 0
        // Authority bytes [2..33], hook bytes [34..65] — no Some/None tags.
        self::assertSame($authority->toBytes()[0], $data[2]);
        self::assertSame($authority->toBytes()[31], $data[33]);
        self::assertSame($hook->toBytes()[0], $data[34]);
        self::assertSame($hook->toBytes()[31], $data[65]);
    }

    #[Test]
    public function transfer_hook_init_encodes_null_fields_as_all_zero_pubkeys(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeTransferHookInstruction($mint, null, null);
        $data = $ix->data->toArray();

        self::assertCount(66, $data);
        self::assertSame(array_fill(0, 32, 0), array_slice($data, 2, 32));
        self::assertSame(array_fill(0, 32, 0), array_slice($data, 34, 32));
    }

    #[Test]
    public function confidential_transfer_mint_uses_optional_non_zero_pubkey_layout(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();
        $authority = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeConfidentialTransferMintInstruction(
            mint: $mint,
            authority: $authority,
            autoApproveNewAccounts: true,
            auditorElGamalPubkey: null,
        );
        $data = $ix->data->toArray();

        // 1 (ins) + 1 (sub) + 32 (authority) + 1 (bool) + 32 (auditor) = 67
        self::assertCount(67, $data);
        self::assertSame(TokenInstruction::ConfidentialTransferExtension, $data[0]);
        self::assertSame(0, $data[1]);                                  // sub-discriminator 0
        // Authority occupies bytes [2..33].
        self::assertSame($authority->toBytes()[0], $data[2]);
        self::assertSame($authority->toBytes()[31], $data[33]);
        self::assertSame(1, $data[34]);                                 // auto_approve = true
        // Auditor encoded as all-zero 32 bytes (= None).
        self::assertSame(array_fill(0, 32, 0), array_slice($data, 35, 32));
    }

    #[Test]
    public function confidential_transfer_mint_encodes_null_authority_as_all_zero_pubkey(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeConfidentialTransferMintInstruction(
            mint: $mint,
            authority: null,
            autoApproveNewAccounts: false,
            auditorElGamalPubkey: null,
        );
        $data = $ix->data->toArray();

        self::assertCount(67, $data);
        // Authority slot is 32 zero bytes when None.
        self::assertSame(array_fill(0, 32, 0), array_slice($data, 2, 32));
        self::assertSame(0, $data[34]);                                 // auto_approve = false
    }

    #[Test]
    public function initialize_mint_close_authority_encodes_coption_none(): void
    {
        $program = new Token2022Program();
        $mint = Keypair::generate()->getPublicKey();

        $ix = $program->createInitializeMintCloseAuthorityInstruction($mint, null);
        $data = $ix->data->toArray();

        self::assertCount(2, $data);
        self::assertSame(0, $data[1]);                          // COption::None tag
    }
}
