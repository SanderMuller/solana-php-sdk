<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

/**
 * Builder for the SPL Memo program.
 *
 * The Memo program writes arbitrary UTF-8 bytes into a transaction's log
 * output. It has no instruction discriminator — `data` is the raw memo.
 * Accounts in the instruction are interpreted as signer attestations; the
 * runtime verifies each is in fact a signer of the transaction.
 *
 * @see https://spl.solana.com/memo
 */
final class MemoProgram
{
    /** Memo program v2 (current). */
    public const string PROGRAM_ID = 'MemoSq4gqABAXKb96qnH8TysNcWxMyWCqXgDLGmfcHr';

    /** Memo program v1 (legacy, still on mainnet). */
    public const string PROGRAM_ID_V1 = 'Memo1UhkJRfHyvLMcVucJwxXeuD728EqVDDwQDxFMNo';

    public static function programId(): PublicKey
    {
        return PublicKey::from(self::PROGRAM_ID);
    }

    public static function programIdV1(): PublicKey
    {
        return PublicKey::from(self::PROGRAM_ID_V1);
    }

    /**
     * Build a memo instruction. `$signers` is the optional list of public keys
     * the runtime should verify as transaction signers (Memo v2 behaviour).
     *
     * @param array<PublicKey> $signers
     *
     * @throws InputValidationException when `$memo` is not valid UTF-8
     */
    public static function build(string $memo, array $signers = [], ?PublicKey $programId = null): TransactionInstruction
    {
        if ($memo !== '' && ! mb_check_encoding($memo, 'UTF-8')) {
            throw new InputValidationException('Memo data must be valid UTF-8.');
        }

        $keys = [];
        foreach ($signers as $signer) {
            $keys[] = new AccountMeta($signer, isSigner: true, isWritable: false);
        }

        return new TransactionInstruction(
            programId: $programId ?? self::programId(),
            keys: $keys,
            data: Buffer::fromString($memo),
        );
    }
}
