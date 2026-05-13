<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions;

use SanderMuller\SolanaPhpSdk\Programs\Token2022Program;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

/**
 * Token-2022 extension instruction builders. Discriminators (and where
 * applicable, sub-discriminators) match `spl_token_2022::instruction`
 * verbatim. Covers the most-used extensions:
 *
 * - immutable-owner, non-transferable mint, permanent delegate,
 *   mint-close-authority — simple init payloads
 * - transfer-fee config — `TransferFeeExtension` sub-discriminator 0
 * - memo-transfer init + enable/disable — `MemoTransferExtension`
 * - transfer-hook init — `TransferHookExtension` sub-discriminator 0
 * - confidential-transfer mint init — `ConfidentialTransferExtension`
 *   sub-discriminator 0 (full flow additionally requires the ZK
 *   ElGamal Proof program; out of scope here)
 *
 * Used by {@see Token2022Program} and any class that exposes
 * `self::TOKEN_PROGRAM_ID` — the trait reads the program-id off the
 * consuming class.
 *
 * @see https://spl.solana.com/token-2022/extensions
 */
trait Token2022Extensions
{
    /**
     * Mark a fresh token account as immutable-owner. Must precede
     * `InitializeAccount`. No args.
     */
    public function createInitializeImmutableOwnerInstruction(
        PublicKey $account,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [new AccountMeta($account, isSigner: false, isWritable: true)],
            data: Buffer::fromArray([TokenInstruction::InitializeImmutableOwner]),
        );
    }

    /**
     * Mark a freshly allocated mint as non-transferable. Must precede
     * `InitializeMint`. No args.
     */
    public function createInitializeNonTransferableMintInstruction(
        PublicKey $mint,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [new AccountMeta($mint, isSigner: false, isWritable: true)],
            data: Buffer::fromArray([TokenInstruction::InitializeNonTransferableMint]),
        );
    }

    /**
     * Assign a permanent delegate to a mint. The delegate retains
     * unconditional transfer + burn authority over every token account
     * of the mint, forever. Must precede `InitializeMint`.
     */
    public function createInitializePermanentDelegateInstruction(
        PublicKey $mint,
        PublicKey $delegate,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        $data = [TokenInstruction::InitializePermanentDelegate];
        foreach ($delegate->toBytes() as $byte) {
            $data[] = $byte;
        }

        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [new AccountMeta($mint, isSigner: false, isWritable: true)],
            data: Buffer::fromArray($data),
        );
    }

    /**
     * Attach a close-authority to a mint so the issuer can later destroy
     * an empty mint via `CloseAccount`. Pass `null` to clear the
     * authority (encoded as the option `None` tag).
     */
    public function createInitializeMintCloseAuthorityInstruction(
        PublicKey $mint,
        ?PublicKey $closeAuthority,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        // Layout: u8 discriminator | u8 COption tag | optional 32-byte pubkey
        $data = [TokenInstruction::InitializeMintCloseAuthority];
        if ($closeAuthority instanceof PublicKey) {
            $data[] = 1;
            foreach ($closeAuthority->toBytes() as $byte) {
                $data[] = $byte;
            }
        } else {
            $data[] = 0;
        }

        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [new AccountMeta($mint, isSigner: false, isWritable: true)],
            data: Buffer::fromArray($data),
        );
    }

    /**
     * Initialize the transfer-fee extension on a freshly allocated mint.
     * Must precede `InitializeMint`. The fee is expressed in basis points
     * (1 bp = 0.01%); `$maximumFee` caps the absolute fee per transfer in
     * raw token units.
     *
     * Wire layout (extension 26, sub-discriminator 0):
     *   u8  instruction = 26
     *   u8  sub         = 0
     *   COption<Pubkey> transfer_fee_config_authority   // 1 + 32 if Some
     *   COption<Pubkey> withdraw_withheld_authority     // 1 + 32 if Some
     *   u16 transfer_fee_basis_points                   // little-endian
     *   u64 maximum_fee                                 // little-endian
     */
    public function createInitializeTransferFeeConfigInstruction(
        PublicKey $mint,
        ?PublicKey $transferFeeConfigAuthority,
        ?PublicKey $withdrawWithheldAuthority,
        int $transferFeeBasisPoints,
        int $maximumFee,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        $data = [TokenInstruction::TransferFeeExtension, 0];
        self::appendCOptionPubkey($data, $transferFeeConfigAuthority);
        self::appendCOptionPubkey($data, $withdrawWithheldAuthority);
        // u16 LE
        $data[] = $transferFeeBasisPoints & 0xFF;
        $data[] = ($transferFeeBasisPoints >> 8) & 0xFF;
        // u64 LE
        for ($shift = 0; $shift < 64; $shift += 8) {
            $data[] = ($maximumFee >> $shift) & 0xFF;
        }

        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [new AccountMeta($mint, isSigner: false, isWritable: true)],
            data: Buffer::fromArray($data),
        );
    }

    /**
     * Toggle memo-transfer enforcement on a token account.
     *
     * `RequiredMemoTransfersInstruction` exposes exactly two variants and
     * NO separate `Initialize` step — `Enable` (sub 0) both activates the
     * extension and turns the requirement on; `Disable` (sub 1) clears
     * it. Calling `Enable` on a fresh account also initializes the
     * extension into the account's state vector, so this single builder
     * covers both the "initialize + turn on" and "turn off" flows.
     *
     * Wire layout: `u8 instruction = 30` + `u8 sub` (0=Enable, 1=Disable).
     */
    public function createMemoTransferToggleInstruction(
        PublicKey $tokenAccount,
        PublicKey $owner,
        bool $enable,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        // sub-discriminator 0 = Enable (also initializes the extension),
        //                    1 = Disable.
        $sub = $enable ? 0 : 1;

        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [
                new AccountMeta($tokenAccount, isSigner: false, isWritable: true),
                new AccountMeta($owner, isSigner: true, isWritable: false),
            ],
            data: Buffer::fromArray([TokenInstruction::MemoTransferExtension, $sub]),
        );
    }

    /**
     * Append a `COption<Pubkey>` (1-byte Some/None tag plus optional 32-byte
     * pubkey). Used by extensions whose Rust layout literally is
     * `COption<Pubkey>` — transfer-fee config, transfer-hook init.
     *
     * @param array<int, int> $data Mutated in place.
     */
    private static function appendCOptionPubkey(array &$data, ?PublicKey $pubkey): void
    {
        if ($pubkey instanceof PublicKey) {
            $data[] = 1;
            foreach ($pubkey->toBytes() as $byte) {
                $data[] = $byte;
            }
        } else {
            $data[] = 0;
        }
    }

    /**
     * Append an `OptionalNonZeroPubkey` (32-byte slot where the all-zero
     * pubkey encodes `None`). Used by confidential-transfer mint init,
     * where the SPL Token-2022 program writes the field as a plain
     * `Pubkey` rather than a `COption<Pubkey>`.
     *
     * @param array<int, int> $data Mutated in place.
     */
    private static function appendOptionalNonZeroPubkey(array &$data, ?PublicKey $pubkey): void
    {
        $bytes = $pubkey instanceof PublicKey ? $pubkey->toBytes() : array_fill(0, 32, 0);
        foreach ($bytes as $byte) {
            $data[] = $byte;
        }
    }

    /**
     * Initialize the transfer-hook extension on a mint. Every transfer of
     * the mint will CPI into `$hookProgramId`, which must conform to the
     * SPL Transfer-Hook interface. Pass `null` for either field to encode
     * the all-zero "None" pubkey.
     *
     * Wire layout (extension 36, sub-discriminator 0):
     *   u8  instruction = 36
     *   u8  sub         = 0
     *   OptionalNonZeroPubkey authority         // 32 bytes, all-zero = None
     *   OptionalNonZeroPubkey hook_program_id   // 32 bytes, all-zero = None
     *
     * The SPL Transfer-Hook `InitializeInstructionData` is a POD struct of
     * two `OptionalNonZeroPubkey` fields — NOT `COption<Pubkey>`, which
     * would prefix each field with a 1-byte Some/None tag.
     */
    public function createInitializeTransferHookInstruction(
        PublicKey $mint,
        ?PublicKey $authority,
        ?PublicKey $hookProgramId,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        $data = [TokenInstruction::TransferHookExtension, 0];
        self::appendOptionalNonZeroPubkey($data, $authority);
        self::appendOptionalNonZeroPubkey($data, $hookProgramId);

        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [new AccountMeta($mint, isSigner: false, isWritable: true)],
            data: Buffer::fromArray($data),
        );
    }

    /**
     * Initialize the confidential-transfer extension on a mint. The full
     * confidential-transfer flow additionally requires submitting
     * zero-knowledge proofs through the ZK ElGamal Proof program; those
     * are out of scope here. This builder pins the initialize-only
     * ceremony so the extension byte appears in the mint's account-state
     * vector.
     *
     * Wire layout (extension 27, sub-discriminator 0):
     *   u8  instruction = 27
     *   u8  sub         = 0
     *   OptionalNonZeroPubkey         authority                 // 32 bytes, all-zero = None
     *   PodBool                       auto_approve_new_accounts // 1 byte
     *   OptionalNonZeroElGamalPubkey  auditor_elgamal_pubkey    // 32 bytes, all-zero = None
     *
     * `OptionalNonZeroPubkey` is a plain 32-byte slot where the all-zero
     * pubkey encodes `None` — NOT a `COption<Pubkey>` (which would
     * prefix a 1-byte tag).
     */
    public function createInitializeConfidentialTransferMintInstruction(
        PublicKey $mint,
        ?PublicKey $authority,
        bool $autoApproveNewAccounts,
        ?PublicKey $auditorElGamalPubkey,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        $data = [TokenInstruction::ConfidentialTransferExtension, 0];
        self::appendOptionalNonZeroPubkey($data, $authority);
        $data[] = $autoApproveNewAccounts ? 1 : 0;
        self::appendOptionalNonZeroPubkey($data, $auditorElGamalPubkey);

        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [new AccountMeta($mint, isSigner: false, isWritable: true)],
            data: Buffer::fromArray($data),
        );
    }

    public function createCreateNativeMintInstruction(
        PublicKey $payer,
        PublicKey $nativeMint,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [
                new AccountMeta($payer, isSigner: true, isWritable: true),
                new AccountMeta($nativeMint, isSigner: false, isWritable: true),
                new AccountMeta(PublicKey::default(), isSigner: false, isWritable: false),
            ],
            data: Buffer::fromArray([TokenInstruction::CreateNativeMint]),
        );
    }
}
