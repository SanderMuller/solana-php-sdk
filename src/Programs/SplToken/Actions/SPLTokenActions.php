<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SplToken\Actions;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Exceptions\TokenInvalidAccountOwnerError;
use Collectiq\SolanaPhpSdk\Exceptions\TokenInvalidMintError;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Programs\SplToken\State\Account;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Transaction;
use Collectiq\SolanaPhpSdk\Util\Commitment;
use Collectiq\SolanaPhpSdk\Util\ConfirmOptions;
use Collectiq\SolanaPhpSdk\Util\Signer;
use Exception;

trait SPLTokenActions
{
    public function getOrCreateAssociatedTokenAccount(
        Connection      $connection,
        Signer|Keypair  $payer,
        PublicKey       $mint,
        PublicKey       $owner,
        bool            $allowOwnerOffCurve = true,
        ?Commitment     $commitment = null,
        ?ConfirmOptions $confirmOptions = null,
        ?PublicKey      $programId = null,
        ?PublicKey      $associatedTokenProgramId = null,
    ): Account {
        $programId ??= PublicKey::fromString(self::TOKEN_PROGRAM_ID);
        $associatedTokenProgramId ??= PublicKey::fromString(self::ASSOCIATED_TOKEN_PROGRAM_ID);

        $associatedToken = $this->getAssociatedTokenAddressSync(
            mint: $mint,
            owner: $owner,
            allowOwnerOffCurve: $allowOwnerOffCurve,
            programId: $programId,
            atPid: $associatedTokenProgramId,
        );

        try {
            $account = Account::getAccount($connection, $associatedToken);
        } catch (Exception $exception) {
            if ($exception instanceof AccountNotFoundException || $exception instanceof TokenInvalidAccountOwnerError) {
                $transaction = new Transaction();
                $transaction->addInstructions(
                    $this->createAssociatedTokenAccountInstruction(
                        payer: $payer->getPublicKey(),
                        associatedToken: $associatedToken,
                        owner: $owner,
                        mint: $mint,
                        programId: $programId,
                        associatedTokenProgramId: $associatedTokenProgramId,
                    )
                );

                if (! $confirmOptions instanceof ConfirmOptions) {
                    $confirmOptions = new ConfirmOptions();
                }

                $transaction->feePayer = $payer->getPublicKey();
                $txnHash = $connection->sendTransaction($transaction, [$payer]);
                $account = Account::getAccount($connection, $associatedToken);
            } else {
                throw $exception;
            }
        }

        if ($account->mint != $mint) {
            throw new TokenInvalidMintError(
                $account->mint->toBase58() . ' != ' . $mint->toBase58()
            );
        }

        if ($account->owner != $owner) {
            throw new TokenInvalidAccountOwnerError(
                $account->owner->toBase58() . ' != ' . $owner->toBase58()
            );
        }

        return $account;
    }
}
