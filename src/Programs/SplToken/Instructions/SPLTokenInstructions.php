<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SplToken\Instructions;

use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;

trait SPLTokenInstructions
{
    public function createAssociatedTokenAccountInstruction(
        PublicKey  $payer,
        PublicKey  $associatedToken,
        PublicKey  $owner,
        PublicKey  $mint,
        ?PublicKey $programId = null,
        ?PublicKey $associatedTokenProgramId = null,
    ): TransactionInstruction {
        return $this->buildAssociatedTokenAccountInstruction(
            payer: $payer,
            associatedToken: $associatedToken,
            owner: $owner,
            mint: $mint,
            instructionData: Buffer::empty(),
            programId: $programId ?? PublicKey::fromString(self::TOKEN_PROGRAM_ID),
            associatedTokenProgramId: $associatedTokenProgramId ?? PublicKey::fromString(self::ASSOCIATED_TOKEN_PROGRAM_ID),
        );
    }

    public function buildAssociatedTokenAccountInstruction(
        PublicKey  $payer,
        PublicKey  $associatedToken,
        PublicKey  $owner,
        PublicKey  $mint,
        Buffer     $instructionData,
        ?PublicKey $programId = null,
        ?PublicKey $associatedTokenProgramId = null,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $associatedTokenProgramId ?? PublicKey::fromString(self::ASSOCIATED_TOKEN_PROGRAM_ID),
            keys: [
                new AccountMeta($payer, true, true),
                new AccountMeta($associatedToken, false, true),
                new AccountMeta($owner, false, false),
                new AccountMeta($mint, false, false),
                new AccountMeta(SystemProgram::programId(), false, false),
                new AccountMeta($programId ?? PublicKey::fromString(self::TOKEN_PROGRAM_ID), false, false),
            ],
            data: $instructionData,
        );
    }

    public function createSyncNativeInstruction(PublicKey $owner, string $programId = self::TOKEN_PROGRAM_ID): TransactionInstruction
    {
        return new TransactionInstruction(
            programId: PublicKey::fromString($programId),
            keys: [
                new AccountMeta(publicKey: $owner, isSigner: false, isWritable: true),
            ],
            data: str_repeat("\0", TokenInstruction::SyncNative),
        );
    }
}
