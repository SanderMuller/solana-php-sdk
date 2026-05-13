<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Programs\SNS\Bindings;
use SanderMuller\SolanaPhpSdk\Programs\SNS\Instructions\Instructions;
use SanderMuller\SolanaPhpSdk\Programs\SNS\Utils;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SensitiveParameter;

final class SnsProgram implements Program
{
    use Bindings;
    use Instructions;
    use IsProgram;
    use Utils;

    // `$config` shape is declared on the `Utils` trait.

    public PublicKey $centralStateSNSRecords;

    public const string SYSVAR_RENT_PUBKEY = 'SysvarRent111111111111111111111111111111111';

    /**
     * @param array{
     *     NAME_PROGRAM_ID: string,
     *     REGISTER_PROGRAM_ID: string,
     *     ROOT_DOMAIN_ACCOUNT: string,
     *     REVERSE_LOOKUP_CLASS: string,
     *     SYSVAR_RENT_PUBKEY: string,
     *     HASH_PREFIX: string,
     * }|null $config
     * @throws InputValidationException
     */
    public function __construct(
        #[SensitiveParameter] ?array $config = null,
    ) {
        $this->config = $config ?? $this->loadConstants();

        $sns_records_id = PublicKey::from('HP3D4D1ZCmohQGFVms2SS4LCANgJyksBf5s1F77FuFjZ');

        $this->centralStateSNSRecords = PublicKey::findProgramAddressSync(
            [$sns_records_id],
            $sns_records_id
        )[0];
    }
}
