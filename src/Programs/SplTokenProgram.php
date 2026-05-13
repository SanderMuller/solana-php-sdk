<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\Programs\SplToken\Actions\SPLTokenActions;
use SanderMuller\SolanaPhpSdk\Programs\SplToken\Instructions\SPLTokenInstructions;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Ata;

final class SplTokenProgram implements Program
{
    use IsProgram;
    use SPLTokenActions;
    use SPLTokenInstructions;

    public const string
        TOKEN_PROGRAM_ID = 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
        ASSOCIATED_TOKEN_PROGRAM_ID = 'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL';

    public function getTokenAccountsByOwner(string $pubKey): mixed
    {
        return $this->client->call('getTokenAccountsByOwner', [
            $pubKey,
            [
                'programId' => self::TOKEN_PROGRAM_ID,
            ],
            [
                'encoding' => 'jsonParsed',
            ],
        ]);
    }

    public function getAssociatedTokenAddressSync(
        PublicKey  $mint,
        PublicKey  $owner,
        bool       $allowOwnerOffCurve = false,
        ?PublicKey $programId = null,
        ?PublicKey $atPid = null,
    ): PublicKey {
        return Ata::derive(
            owner: $owner,
            mint: $mint,
            tokenProgram: $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID),
            allowOwnerOffCurve: $allowOwnerOffCurve,
            associatedTokenProgram: $atPid ?? PublicKey::from(self::ASSOCIATED_TOKEN_PROGRAM_ID),
        );
    }
}
