<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\DataObjects\CreateLookupTableResult;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\TransactionInstruction;
use SanderMuller\SolanaPhpSdk\Util\AccountMeta;

/**
 * Builders for the Address Lookup Table (ALT) program.
 *
 * ALT instructions are bincode-encoded with a u32 LE discriminator. Vec
 * lengths are serialised as u64 LE per Solana's `serialize_with_length`
 * convention. The discriminators below mirror
 * `solana_address_lookup_table_program::instruction::ProgramInstruction`.
 *
 * @see https://docs.solanalabs.com/proposals/versioned-transactions
 */
final class AddressLookupTableProgram
{
    public const string PROGRAM_ID = 'AddressLookupTab1e1111111111111111111111111';

    public const string SYSTEM_PROGRAM_ID = '11111111111111111111111111111111';

    private const int IX_CREATE = 0;

    private const int IX_FREEZE = 1;

    private const int IX_EXTEND = 2;

    private const int IX_DEACTIVATE = 3;

    private const int IX_CLOSE = 4;

    public static function programId(): PublicKey
    {
        return PublicKey::from(self::PROGRAM_ID);
    }

    /**
     * Derive the canonical lookup-table address for `(authority, recentSlot)`.
     *
     * @return array{0: PublicKey, 1: int} [pda, bumpSeed]
     *
     * @throws InputValidationException
     */
    public static function deriveLookupTableAddress(PublicKey $authority, int $recentSlot): array
    {
        return PublicKey::findProgramAddress(
            [
                $authority->toBytes(),
                self::u64($recentSlot),
            ],
            self::programId(),
        );
    }

    /**
     * Create an empty lookup table at the canonical PDA for `(authority, recentSlot)`.
     * `recentSlot` must be a slot the validator can still observe; pass a value
     * from `Connection::getSlot()`.
     *
     * @throws InputValidationException
     */
    public static function createLookupTable(
        PublicKey $authority,
        PublicKey $payer,
        int $recentSlot,
    ): CreateLookupTableResult {
        [$lookupTableAddress, $bumpSeed] = self::deriveLookupTableAddress($authority, $recentSlot);

        $instruction = new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($lookupTableAddress, isSigner: false, isWritable: true),
                new AccountMeta($authority, isSigner: true, isWritable: false),
                new AccountMeta($payer, isSigner: true, isWritable: true),
                new AccountMeta(PublicKey::from(self::SYSTEM_PROGRAM_ID), isSigner: false, isWritable: false),
            ],
            data: array_merge(
                self::u32(self::IX_CREATE),
                self::u64($recentSlot),
                [$bumpSeed & 0xFF],
            ),
        );

        return new CreateLookupTableResult($instruction, $lookupTableAddress, $bumpSeed);
    }

    public static function freezeLookupTable(
        PublicKey $lookupTable,
        PublicKey $authority,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($lookupTable, isSigner: false, isWritable: true),
                new AccountMeta($authority, isSigner: true, isWritable: false),
            ],
            data: self::u32(self::IX_FREEZE),
        );
    }

    /**
     * Append `$addresses` to a lookup table. Lookup tables cap at 256 entries
     * — callers are responsible for honouring the limit across calls.
     *
     * @param array<PublicKey> $addresses
     */
    public static function extendLookupTable(
        PublicKey $lookupTable,
        PublicKey $authority,
        PublicKey $payer,
        array $addresses,
    ): TransactionInstruction {
        $data = self::u32(self::IX_EXTEND);
        $data = array_merge($data, self::u64(count($addresses)));
        foreach ($addresses as $address) {
            foreach ($address->toBytes() as $byte) {
                $data[] = $byte;
            }
        }

        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($lookupTable, isSigner: false, isWritable: true),
                new AccountMeta($authority, isSigner: true, isWritable: false),
                new AccountMeta($payer, isSigner: true, isWritable: true),
                new AccountMeta(PublicKey::from(self::SYSTEM_PROGRAM_ID), isSigner: false, isWritable: false),
            ],
            data: $data,
        );
    }

    public static function deactivateLookupTable(
        PublicKey $lookupTable,
        PublicKey $authority,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($lookupTable, isSigner: false, isWritable: true),
                new AccountMeta($authority, isSigner: true, isWritable: false),
            ],
            data: self::u32(self::IX_DEACTIVATE),
        );
    }

    public static function closeLookupTable(
        PublicKey $lookupTable,
        PublicKey $authority,
        PublicKey $recipient,
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($lookupTable, isSigner: false, isWritable: true),
                new AccountMeta($authority, isSigner: true, isWritable: false),
                new AccountMeta($recipient, isSigner: false, isWritable: true),
            ],
            data: self::u32(self::IX_CLOSE),
        );
    }

    /**
     * @return array<int, int>
     */
    private static function u32(int $value): array
    {
        return [
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function u64(int $value): array
    {
        return [
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
            ($value >> 32) & 0xFF,
            ($value >> 40) & 0xFF,
            ($value >> 48) & 0xFF,
            ($value >> 56) & 0xFF,
        ];
    }
}
