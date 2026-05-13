<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\Exceptions\TokenOwnerOffCurveError;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\Actions\SPLTokenActions;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions\SPLTokenInstructions;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions\Token2022Extensions;
use SanderMuller\SolanaPhpSdk\PublicKey;

/**
 * Builders for the Token-2022 (Token Extensions) program.
 *
 * Token-2022 inherits every instruction discriminator from legacy SPL Token
 * for the core operations (initialize-mint, transfer-checked, burn, mint-to,
 * close-account, set-authority, …). Only the program-id differs, so this
 * class reuses {@see SPLTokenInstructions} verbatim and swaps the
 * `TOKEN_PROGRAM_ID` constant the trait reads via `self::`. Extension
 * instruction builders (transfer-fee, memo-transfer, transfer-hook,
 * confidential-transfer init, …) live on {@see Token2022Extensions} and
 * are mixed in below.
 *
 * @see https://spl.solana.com/token-2022
 */
final class Token2022Program implements Program
{
    use IsProgram;
    use SPLTokenActions;
    use SPLTokenInstructions;
    use Token2022Extensions;

    public const string TOKEN_PROGRAM_ID = 'TokenzQdBNbLqP5VEhdkAS6EPFLC1PHnBqCXEpPxuEb';

    public const string ASSOCIATED_TOKEN_PROGRAM_ID = 'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL';

    public function getTokenAccountsByOwner(string $pubKey): mixed
    {
        return $this->client->call('getTokenAccountsByOwner', [
            $pubKey,
            ['programId' => self::TOKEN_PROGRAM_ID],
            ['encoding' => 'jsonParsed'],
        ]);
    }

    /**
     * Derive the Associated Token Account address for `(owner, mint)` under
     * the Token-2022 program. The ATA seed list includes the token-program
     * id, so the Token-2022 ATA address differs from the legacy SPL Token
     * ATA address even for the same `(owner, mint)` pair.
     */
    public function getAssociatedTokenAddressSync(
        PublicKey $mint,
        PublicKey $owner,
        bool $allowOwnerOffCurve = false,
        ?PublicKey $programId = null,
        ?PublicKey $atPid = null,
    ): PublicKey {
        if (! $allowOwnerOffCurve && ! PublicKey::isOnCurve($owner->toBinaryString())) {
            throw new TokenOwnerOffCurveError();
        }

        $tokenProgramId = $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID);
        $ataProgramId = $atPid ?? PublicKey::from(self::ASSOCIATED_TOKEN_PROGRAM_ID);

        $result = PublicKey::findProgramAddressSync(
            seeds: [
                $owner->getBuffer(),
                $tokenProgramId->getBuffer(),
                $mint->getBuffer(),
            ],
            programId: $ataProgramId,
        );

        return $result[0];
    }
}
