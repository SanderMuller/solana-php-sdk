<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs;

use SanderMuller\SolanaPhpSdk\Accounts\DidData;
use SanderMuller\SolanaPhpSdk\Did\DiDUri;
use SanderMuller\SolanaPhpSdk\Enum\Network;
use SanderMuller\SolanaPhpSdk\Exceptions\SolanaPhpSdkException;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;
use StephenHill\Base58;

final class DidSolProgram implements Program
{
    use IsProgram;

    private const string DIDSOL_PROGRAM_ID = 'didso1Dpqpm4CsiCjzP766BGY89CAdD6ZBL68cRhFPc';

    private const string DIDSOL_DEFAULT_SEED = 'did-account';

    /**
     * Fetch the on-chain account info for the DID data account associated with
     * $base58SubjectPk. Returns the RPC `value` payload (account info dict) or
     * null when the account does not exist.
     *
     * @return array<string, mixed>|null
     * @throws SolanaPhpSdkException
     */
    public static function getDidDataAcccountInfo(SolanaRpcClient $client, string $base58SubjectPk): ?array
    {
        $pdaPublicKey = self::getDidDataAccountId($base58SubjectPk);

        $response = $client->call('getAccountInfo', [$pdaPublicKey, ['encoding' => 'jsonParsed']]);

        if (! is_array($response)) {
            return null;
        }

        $value = $response['value'] ?? null;
        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Derive the DID data account PDA for $base58SubjectPk.
     *
     * @throws SolanaPhpSdkException
     */
    public static function getDidDataAccountId(string $base58SubjectPk): string
    {
        $seeds = [
            self::DIDSOL_DEFAULT_SEED,
            new Base58()->decode($base58SubjectPk),
        ];

        $pId = PublicKey::from(self::DIDSOL_PROGRAM_ID);
        $publicKey = PublicKey::findProgramAddress($seeds, $pId);

        return $publicKey[0]->toBase58();
    }

    /**
     * Decode a base64-encoded DID data account payload into a populated
     * {@see DidData}, replacing the raw byte array `keyData` with its base58
     * encoding (matching the on-chain authority pubkey representation).
     */
    public static function deserializeDidData(string $dataBase64): DidData
    {
        $binary = base64_decode($dataBase64, true);
        if ($binary === false) {
            throw new SolanaPhpSdkException('DID data is not valid base64.');
        }

        $unpacked = unpack('C*', $binary);
        /** @var array<int, int> $uint8Array */
        $uint8Array = $unpacked === false ? [] : array_values($unpacked);

        $didData = DidData::fromBuffer($uint8Array);

        $keyData = $didData->keyData;
        if (! is_array($keyData)) {
            return $didData;
        }

        $didData->keyData = new Base58()->encode(pack('C*', ...$keyData));

        return $didData;
    }

    /**
     * @return array{network: Network, base58SubjectPK: string|null, dataAccountId: string, rpcEndpoint: string}
     * @throws SolanaPhpSdkException
     */
    public function parse(DiDUri $did): array
    {
        $pk = $did->base58SubjectPK();

        if ($pk === null) {
            throw new SolanaPhpSdkException('DID URI is missing the subject public key.');
        }

        $network = $did->toNetwork();

        return [
            'network' => $network,
            'base58SubjectPK' => $pk,
            'dataAccountId' => self::getDidDataAccountId($pk),
            'rpcEndpoint' => $network->rpcEndpoint(),
        ];
    }
}
