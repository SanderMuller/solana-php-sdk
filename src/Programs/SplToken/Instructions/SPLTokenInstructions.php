<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions;

use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

/**
 * Builders for SPL Token program instructions. Layouts mirror
 * `@solana/spl-token`. Pass the Token-2022 program id explicitly to target
 * the newer program.
 */
trait SPLTokenInstructions
{
    public function createAssociatedTokenAccountInstruction(
        PublicKey $payer,
        PublicKey $associatedToken,
        PublicKey $owner,
        PublicKey $mint,
        ?PublicKey $programId = null,
        ?PublicKey $associatedTokenProgramId = null,
    ): TransactionInstruction {
        return $this->buildAssociatedTokenAccountInstruction(
            payer: $payer,
            associatedToken: $associatedToken,
            owner: $owner,
            mint: $mint,
            instructionData: Buffer::empty(),
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            associatedTokenProgramId: $associatedTokenProgramId ?? PublicKey::from(self::ASSOCIATED_TOKEN_PROGRAM_ID),
        );
    }

    public function buildAssociatedTokenAccountInstruction(
        PublicKey $payer,
        PublicKey $associatedToken,
        PublicKey $owner,
        PublicKey $mint,
        Buffer $instructionData,
        ?PublicKey $programId = null,
        ?PublicKey $associatedTokenProgramId = null,
    ): TransactionInstruction {
        $programId ??= PublicKey::from(self::TOKEN_PROGRAM_ID);
        $associatedTokenProgramId ??= PublicKey::from(self::ASSOCIATED_TOKEN_PROGRAM_ID);

        return new TransactionInstruction(
            programId: $associatedTokenProgramId,
            keys: [
                new AccountMeta($payer, true, true),
                new AccountMeta($associatedToken, false, true),
                new AccountMeta($owner, false, false),
                new AccountMeta($mint, false, false),
                new AccountMeta(SystemProgram::programId(), false, false),
                new AccountMeta($programId, false, false),
            ],
            data: $instructionData,
        );
    }

    public function createSyncNativeInstruction(PublicKey $owner, ?PublicKey $programId = null): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [
                new AccountMeta(publicKey: $owner, isSigner: false, isWritable: true),
            ],
            data: Buffer::fromArray([TokenInstruction::SyncNative]),
        );
    }

    /**
     * Create an InitializeMint2 instruction (no rent sysvar required).
     */
    public function createInitializeMintInstruction(
        PublicKey $mint,
        int $decimals,
        PublicKey $mintAuthority,
        ?PublicKey $freezeAuthority = null,
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: [
                new AccountMeta($mint, false, true),
            ],
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::InitializeMint2, $decimals]),
                Buffer::fromArray($mintAuthority->toBytes()),
                self::optionPubkey($freezeAuthority),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createMintToInstruction(
        PublicKey $mint,
        PublicKey $destination,
        PublicKey $authority,
        int $amount,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($mint, false, true),
                    new AccountMeta($destination, false, true),
                ],
                $authority,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::MintTo]),
                self::u64($amount),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createMintToCheckedInstruction(
        PublicKey $mint,
        PublicKey $destination,
        PublicKey $authority,
        int $amount,
        int $decimals,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($mint, false, true),
                    new AccountMeta($destination, false, true),
                ],
                $authority,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::MintToChecked]),
                self::u64($amount),
                Buffer::fromArray([$decimals]),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createBurnInstruction(
        PublicKey $account,
        PublicKey $mint,
        PublicKey $owner,
        int $amount,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($account, false, true),
                    new AccountMeta($mint, false, true),
                ],
                $owner,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::Burn]),
                self::u64($amount),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createBurnCheckedInstruction(
        PublicKey $account,
        PublicKey $mint,
        PublicKey $owner,
        int $amount,
        int $decimals,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($account, false, true),
                    new AccountMeta($mint, false, true),
                ],
                $owner,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::BurnChecked]),
                self::u64($amount),
                Buffer::fromArray([$decimals]),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createTransferInstruction(
        PublicKey $source,
        PublicKey $destination,
        PublicKey $owner,
        int $amount,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($source, false, true),
                    new AccountMeta($destination, false, true),
                ],
                $owner,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::Transfer]),
                self::u64($amount),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createTransferCheckedInstruction(
        PublicKey $source,
        PublicKey $mint,
        PublicKey $destination,
        PublicKey $owner,
        int $amount,
        int $decimals,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($source, false, true),
                    new AccountMeta($mint, false, false),
                    new AccountMeta($destination, false, true),
                ],
                $owner,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::TransferChecked]),
                self::u64($amount),
                Buffer::fromArray([$decimals]),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createApproveInstruction(
        PublicKey $source,
        PublicKey $delegate,
        PublicKey $owner,
        int $amount,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($source, false, true),
                    new AccountMeta($delegate, false, false),
                ],
                $owner,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::Approve]),
                self::u64($amount),
            ),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createRevokeInstruction(
        PublicKey $source,
        PublicKey $owner,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [new AccountMeta($source, false, true)],
                $owner,
                $multiSigners,
            ),
            data: Buffer::fromArray([TokenInstruction::Revoke]),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createCloseAccountInstruction(
        PublicKey $account,
        PublicKey $destination,
        PublicKey $owner,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($account, false, true),
                    new AccountMeta($destination, false, true),
                ],
                $owner,
                $multiSigners,
            ),
            data: Buffer::fromArray([TokenInstruction::CloseAccount]),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createFreezeAccountInstruction(
        PublicKey $account,
        PublicKey $mint,
        PublicKey $authority,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($account, false, true),
                    new AccountMeta($mint, false, false),
                ],
                $authority,
                $multiSigners,
            ),
            data: Buffer::fromArray([TokenInstruction::FreezeAccount]),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createThawAccountInstruction(
        PublicKey $account,
        PublicKey $mint,
        PublicKey $authority,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [
                    new AccountMeta($account, false, true),
                    new AccountMeta($mint, false, false),
                ],
                $authority,
                $multiSigners,
            ),
            data: Buffer::fromArray([TokenInstruction::ThawAccount]),
        );
    }

    /**
     * @param array<PublicKey> $multiSigners
     */
    public function createSetAuthorityInstruction(
        PublicKey $account,
        PublicKey $currentAuthority,
        int $authorityType,
        ?PublicKey $newAuthority,
        array $multiSigners = [],
        ?PublicKey $programId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            keys: self::withMultisig(
                [new AccountMeta($account, false, true)],
                $currentAuthority,
                $multiSigners,
            ),
            data: Buffer::concat(
                Buffer::fromArray([TokenInstruction::SetAuthority, $authorityType]),
                self::optionPubkey($newAuthority),
            ),
        );
    }

    /**
     * @param array<AccountMeta> $head
     * @param array<PublicKey> $multiSigners
     * @return array<AccountMeta>
     */
    private static function withMultisig(array $head, PublicKey $authority, array $multiSigners): array
    {
        if ($multiSigners === []) {
            $head[] = new AccountMeta($authority, true, false);

            return $head;
        }

        $head[] = new AccountMeta($authority, false, false);
        foreach ($multiSigners as $signer) {
            $head[] = new AccountMeta($signer, true, false);
        }

        return $head;
    }

    private static function u64(int $value): Buffer
    {
        return Buffer::fromArray([
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
            ($value >> 32) & 0xFF,
            ($value >> 40) & 0xFF,
            ($value >> 48) & 0xFF,
            ($value >> 56) & 0xFF,
        ]);
    }

    private static function optionPubkey(?PublicKey $key): Buffer
    {
        if (! $key instanceof PublicKey) {
            return Buffer::fromArray([0]);
        }

        return Buffer::concat(
            Buffer::fromArray([1]),
            Buffer::fromArray($key->toBytes()),
        );
    }
}
