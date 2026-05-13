<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs\SplToken\Actions;

use Exception;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use SanderMuller\SolanaPhpSdk\Exceptions\TokenInvalidAccountOwnerError;
use SanderMuller\SolanaPhpSdk\Exceptions\TokenInvalidMintError;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\State\Account;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Transaction;
use SanderMuller\SolanaPhpSdk\Util\Commitment;
use SanderMuller\SolanaPhpSdk\Util\ConfirmOptions;
use SanderMuller\SolanaPhpSdk\Util\Signer;

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
        $programId ??= PublicKey::from(self::TOKEN_PROGRAM_ID);
        $associatedTokenProgramId ??= PublicKey::from(self::ASSOCIATED_TOKEN_PROGRAM_ID);

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

        $accountMint = $account->mint;
        if (! $accountMint instanceof PublicKey || ! $accountMint->equals($mint)) {
            $found = $accountMint instanceof PublicKey ? $accountMint->toBase58() : 'null';
            throw new TokenInvalidMintError("{$found} != {$mint->toBase58()}");
        }

        $accountOwner = $account->owner;
        if (! $accountOwner instanceof PublicKey || ! $accountOwner->equals($owner)) {
            $found = $accountOwner instanceof PublicKey ? $accountOwner->toBase58() : 'null';
            throw new TokenInvalidAccountOwnerError("{$found} != {$owner->toBase58()}");
        }

        return $account;
    }
}
