<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;

final class SystemProgram implements Program
{
    use IsProgram;

    public const int PROGRAM_INDEX_CREATE_ACCOUNT = 0;

    private const int PROGRAM_INDEX_TRANSFER = 2;

    /**
     * Public key that identifies the System program
     */
    public static function programId(): PublicKey
    {
        return PublicKey::default();
    }

    public function getAccountInfo(string $pubKey): array
    {
        $accountResponse = $this->client->call('getAccountInfo', [$pubKey, ['encoding' => 'jsonParsed']])['value'];

        dd($accountResponse);

        if (! $accountResponse) {
            throw new AccountNotFoundException("API Error: Account {$pubKey} not found.");
        }

        return $accountResponse;
    }

    public function getBalance(string $pubKey): float
    {
        return $this->client->call('getBalance', [$pubKey])['value'];
    }

    public function getConfirmedTransaction(string $transactionSignature): array
    {
        return $this->client->call('getConfirmedTransaction', [$transactionSignature]);
    }

    /**
     * NEW: This method is only available in solana-core v1.7 or newer. Please use getConfirmedTransaction for solana-core v1.6
     */
    public function getTransaction(string $transactionSignature): array
    {
        return $this->client->call('getTransaction', [$transactionSignature]);
    }

    /**
     * Generate a transaction instruction that transfers lamports from one account to another
     */
    public static function transfer(
        PublicKey $fromPubkey,
        PublicKey $toPublicKey,
        int $lamports
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($fromPubkey, true, true),
                new AccountMeta($toPublicKey, false, true),
            ],
            // 4 byte instruction index + 8 bytes lamports
            data: [
                // uint32
                ...unpack('C*', pack('V', self::PROGRAM_INDEX_TRANSFER)),
                // int64
                ...unpack('C*', pack('P', $lamports)),
            ],
        );
    }

    public static function createAccount(
        PublicKey $fromPubkey,
        PublicKey $newAccountPublicKey,
        int $lamports,
        int $space,
        PublicKey $programId
    ): TransactionInstruction {
        return new TransactionInstruction(
            programId: self::programId(),
            keys: [
                new AccountMeta($fromPubkey, true, true),
                new AccountMeta($newAccountPublicKey, true, true),
            ],
            data: [
                // uint32
                ...unpack('C*', pack('V', self::PROGRAM_INDEX_CREATE_ACCOUNT)),
                // int64
                ...unpack('C*', pack('P', $lamports)),
                // int64
                ...unpack('C*', pack('P', $space)),
                //
                ...$programId->toBytes(),
            ],
        );
    }
}
