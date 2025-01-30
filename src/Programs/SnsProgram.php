<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Programs\SNS\Bindings;
use Collectiq\SolanaPhpSdk\Programs\SNS\Instructions\Instructions;
use Collectiq\SolanaPhpSdk\Programs\SNS\Utils;
use Collectiq\SolanaPhpSdk\PublicKey;

final class SnsProgram implements Program
{
    use Bindings;
    use Instructions;
    use IsProgram;
    use Utils;

    public mixed $config;

    public PublicKey $centralStateSNSRecords;

    public const string SYSVAR_RENT_PUBKEY = 'SysvarRent111111111111111111111111111111111';

    /**
     * @throws InputValidationException
     */
    public function __construct(
        #[\SensitiveParameter] $config = null,
    ) {
        $this->config = $config ?: $this->loadConstants();

        $sns_records_id = PublicKey::from('HP3D4D1ZCmohQGFVms2SS4LCANgJyksBf5s1F77FuFjZ');

        $this->centralStateSNSRecords = PublicKey::findProgramAddressSync(
            [$sns_records_id],
            $sns_records_id
        )[0];
    }
}
