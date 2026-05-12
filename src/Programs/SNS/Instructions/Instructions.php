<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SNS\Instructions;

use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;

trait Instructions
{
    public function createInstruction(
        PublicKey  $nameProgramId,
        PublicKey  $systemProgramId,
        PublicKey  $nameKey,
        PublicKey  $nameOwnerKey,
        PublicKey  $payerKey,
        Buffer     $hashed_name,
        Buffer     $lamports,
        Buffer     $space,
        ?PublicKey $nameClassKey = null,
        ?PublicKey $nameParent = null,
        ?PublicKey $nameParentOwner = null,
    ): TransactionInstruction {
        $keys = [
            new AccountMeta(
                publicKey: $systemProgramId,
                isSigner: false,
                isWritable: false,
            ),
            new AccountMeta(
                publicKey: $payerKey,
                isSigner: true,
                isWritable: true,
            ),
            new AccountMeta(
                publicKey: $nameKey,
                isSigner: false,
                isWritable: true,
            ),
            new AccountMeta(
                publicKey: $nameOwnerKey,
                isSigner: false,
                isWritable: false,
            ),
        ];

        if ($nameClassKey instanceof PublicKey) {
            $keys[] = new AccountMeta(
                publicKey: $nameClassKey,
                isSigner: true,
                isWritable: false,
            );
        } else {
            $keys[] = new AccountMeta(
                publicKey: PublicKey::generate(),
                isSigner: false,
                isWritable: false,
            );
        }

        if ($nameParent instanceof PublicKey) {
            $keys[] = new AccountMeta(
                publicKey: $nameParent,
                isSigner: false,
                isWritable: false,
            );
        } else {
            $keys[] = new AccountMeta(
                publicKey: PublicKey::generate(),
                isSigner: false,
                isWritable: false,
            );
        }

        if ($nameParentOwner instanceof PublicKey) {
            $keys[] = new AccountMeta(
                publicKey: $nameParentOwner,
                isSigner: true,
                isWritable: false,
            );
        }

        return new TransactionInstruction(
            programId: $nameProgramId,
            keys: $keys,
            data: Buffer::concat(
                Buffer::fromArray([0]), // Create Instruction code 0
                Buffer::fromInt(
                    value: $hashed_name->length(),
                    datatype: BufferType::INT,
                    isSignedValue: false,
                ),
                $hashed_name,
                $lamports,
                $space,
            )
        );
    }

    /**
     * Updates an instruction.
     *
     * @param PublicKey $nameProgramId The public key of the name program.
     * @param PublicKey $nameAccountKey The public key of the name account.
     * @param Buffer $offset The offset.
     * @param Buffer $input_data The input data.
     * @param PublicKey $nameUpdateSigner The public key of the name update signer.
     * @return TransactionInstruction The created transaction instruction.
     * @throws InputValidationException
     */
    public function updateInstruction(
        PublicKey $nameProgramId,
        PublicKey $nameAccountKey,
        Buffer    $offset,
        Buffer    $input_data,
        PublicKey $nameUpdateSigner,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $nameProgramId,
            keys: [
                new AccountMeta(
                    publicKey: $nameAccountKey,
                    isSigner: false,
                    isWritable: true,
                ),
                new AccountMeta(
                    publicKey: $nameUpdateSigner,
                    isSigner: true,
                    isWritable: false,
                ),
            ],
            data: Buffer::concat(
                Buffer::fromArray([1]),
                $offset,
                Buffer::fromInt(
                    value: $input_data->length(),
                    datatype: BufferType::INT,
                    isSignedValue: false,
                ),
                $input_data,
            )
        );
    }

    public function transferInstruction(
        PublicKey  $nameProgramId,
        PublicKey  $nameAccountKey,
        PublicKey  $newOwnerKey,
        PublicKey  $currentNameOwnerKey,
        ?PublicKey $nameClassKey = null,
        ?PublicKey $nameParent = null,
        ?PublicKey $parentOwner = null
    ): TransactionInstruction {
        $keys = [
            new AccountMeta(
                publicKey: $nameAccountKey,
                isSigner: false,
                isWritable: true,
            ),
            new AccountMeta(
                publicKey: $parentOwner ?? $currentNameOwnerKey,
                isSigner: true,
                isWritable: false,
            ),
        ];

        if ($nameClassKey instanceof PublicKey) {
            $keys[] = new AccountMeta(
                publicKey: $nameClassKey,
                isSigner: true,
                isWritable: false,
            );
        }

        if ($parentOwner instanceof PublicKey && $nameParent instanceof PublicKey) {
            if (! $nameClassKey instanceof PublicKey) {
                $keys[] = new AccountMeta(
                    publicKey: PublicKey::generate(),
                    isSigner: false,
                    isWritable: false,
                );
            }

            $keys[] = new AccountMeta(
                publicKey: $nameParent,
                isSigner: false,
                isWritable: false,
            );
        }

        return new TransactionInstruction(
            programId: $nameProgramId,
            keys: $keys,
            data: Buffer::concat(
                Buffer::fromArray([2]),
                $newOwnerKey->getBuffer(),
            ),
        );
    }

    /**
     * Creates a realloc instruction.
     *
     * @param PublicKey $nameProgramId The public key of the name program.
     * @param PublicKey $systemProgramId The public key of the system program.
     * @param PublicKey $payerKey The public key of the payer.
     * @param PublicKey $nameAccountKey The public key of the name account.
     * @param PublicKey $nameOwnerKey The public key of the name owner.
     * @param Buffer $space A Buffer instance that should represent a 32-bit unsigned integer.
     * @return TransactionInstruction The created transaction instruction.
     * @throws InputValidationException
     */
    public function reallocInstruction(
        PublicKey $nameProgramId,
        PublicKey $systemProgramId,
        PublicKey $payerKey,
        PublicKey $nameAccountKey,
        PublicKey $nameOwnerKey,
        Buffer    $space
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $nameProgramId,
            keys: [
                new AccountMeta(
                    publicKey: $systemProgramId,
                    isSigner: false,
                    isWritable: false,
                ),
                new AccountMeta(
                    publicKey: $payerKey,
                    isSigner: true,
                    isWritable: true,
                ),
                new AccountMeta(
                    publicKey: $nameAccountKey,
                    isSigner: false,
                    isWritable: true,
                ),
                new AccountMeta(
                    publicKey: $nameOwnerKey,
                    isSigner: true,
                    isWritable: false,
                ),
            ],
            data: Buffer::concat(
                Buffer::fromArray([4]),
                $space,
            ),
        );
    }

    /**
     * Creates a delete instruction.
     *
     * @param PublicKey $nameProgramId The public key of the name program.
     * @param PublicKey $nameAccountKey The public key of the name account.
     * @param PublicKey $refundTargetKey The public key of the refund target.
     * @param PublicKey $nameOwnerKey The public key of the name owner.
     * @return TransactionInstruction The created transaction instruction.
     * @throws InputValidationException
     */
    public function deleteInstruction(
        PublicKey $nameProgramId,
        PublicKey $nameAccountKey,
        PublicKey $refundTargetKey,
        PublicKey $nameOwnerKey
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: $nameProgramId,
            keys: [
                new AccountMeta(
                    publicKey: $nameAccountKey,
                    isSigner: false,
                    isWritable: true,
                ),
                new AccountMeta(
                    publicKey: $nameOwnerKey,
                    isSigner: true,
                    isWritable: false,
                ),
                new AccountMeta(
                    publicKey: $refundTargetKey,
                    isSigner: false,
                    isWritable: true,
                ),
            ],
            data: Buffer::concat(
                Buffer::fromArray([3]),
            ),
        );
    }
}
