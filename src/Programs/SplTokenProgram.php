<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\Exceptions\TokenOwnerOffCurveError;
use Collectiq\SolanaPhpSdk\Programs\SplToken\Actions\SPLTokenActions;
use Collectiq\SolanaPhpSdk\Programs\SplToken\Instructions\SPLTokenInstructions;
use Collectiq\SolanaPhpSdk\PublicKey;

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
        if (! $allowOwnerOffCurve && ! PublicKey::isOnCurve($owner->toBinaryString())) {
            throw new TokenOwnerOffCurveError();
        }

        $tokenProgramId = $programId ?? PublicKey::from(self::TOKEN_PROGRAM_ID);
        $ataProgramId = $atPid ?? PublicKey::from(self::ASSOCIATED_TOKEN_PROGRAM_ID);

        $seeds = [
            $owner->getBuffer(),
            $tokenProgramId->getBuffer(),
            $mint->getBuffer(),
        ];

        $result = PublicKey::findProgramAddressSync(
            seeds: $seeds,
            programId: $ataProgramId,
        );

        return $result[0];
    }
}
