<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs;

use Collectiq\SolanaPhpSdk\Accounts\DidData;
use Collectiq\SolanaPhpSdk\Did\DiDUri;
use Collectiq\SolanaPhpSdk\Exceptions\SolanaPhpSdkException;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Services\SolanaRpcClient;
use StephenHill\Base58;

final class DidSolProgram implements Program
{
    use IsProgram;

    private const string DIDSOL_PROGRAM_ID = 'didso1Dpqpm4CsiCjzP766BGY89CAdD6ZBL68cRhFPc';

    private const string DIDSOL_DEFAULT_SEED = 'did-account';

    /**
     * getDidDataAcccountInfo
     *
     * @param SolanaRpcClient|string $client The RPC client or the custom RPC endpoint URL to use.
     * @param string $base58SubjectPk The Public Key of the DID.
     * @return string (JSON) The account info of the DID data account as it comes from the RPC
     * @example DidSolProgram::getDidDataAcccountInfo($client, 'did:sol:3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk', false);
     */
    public static function getDidDataAcccountInfo($client, $base58SubjectPk)
    {
        $pdaPublicKey = self::getDidDataAccountId($base58SubjectPk);

        return $client->call('getAccountInfo', [$pdaPublicKey, ['encoding' => 'jsonParsed']])['value'];
        // Data is always returned in base54 because it exceeds 128 bytes
    }

    /**
     * getDidDataAccountId
     *
     * @param string $did 'did:sol:[cluster]....'
     * @return string The base58 encoded public key of the DID data account
     * @throws SolanaPhpSdkException
     * @example DidSolProgram::getDidDataAccountId('did:sol:devnet:3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk');
     */
    public static function getDidDataAccountId($base58SubjectPk): string
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
     * deserializeDidData
     *
     * @param string $dataBase64 The base64 encoded data of the DID data account
     * @return DidData The deserialized DID data object
     * @example DidSolProgram::deserializeDidData('TVjvjfsd7fMA/gAAAA...');
     */
    public static function deserializeDidData($dataBase64)
    {
        $base64String = base64_decode($dataBase64);
        $uint8Array = array_values(unpack('C*', $base64String));
        $didData = DidData::fromBuffer($uint8Array);

        $keyData = $didData->keyData;

        $binaryString = pack('C*', ...$keyData);

        $b58 = new Base58();
        $base58String = $b58->encode($binaryString);
        $didData->keyData = $base58String;

        return $didData;
    }

    public function parse(DiDUri $did): array
    {
        $pk = $did->base58SubjectPK();

        $network = $did->toNetwork();

        return [
            'network' => $network,
            'base58SubjectPK' => $pk,
            'dataAccountId' => self::getDidDataAccountId($pk),
            'rpcEndpoint' => $network->rpcEndpoint(),
        ];
    }
}
