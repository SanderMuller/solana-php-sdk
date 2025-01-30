<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SNS;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Exceptions\SNSError;
use Collectiq\SolanaPhpSdk\Programs\SNS\State\NameRegistryStateAccount;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\Util\Buffer;

trait Utils
{
    // config.json file should be in the same directory as this file
    public mixed $config;

    // Constructor

    private function loadConstants(): mixed
    {
        $jsonFilePath = dirname(__DIR__) . '/SNS/Constants/config.json';

        return json_decode(file_get_contents($jsonFilePath), true);
    }

    public function getHashedNameSync(string $name): Buffer
    {
        $input = $this->config['HASH_PREFIX'] . $name;

        $hash = hash('sha256', Buffer::fromString($input)->toString(), true);

        return Buffer::from($hash);
    }

    public function getNameAccountKeySync(
        Buffer     $hashed_name,
        ?PublicKey $nameClass = null,
        ?PublicKey $nameParent = null,
    ): PublicKey {
        $seeds = [$hashed_name];

        $seeds[] = $nameClass instanceof PublicKey ? $nameClass : PublicKey::generate();
        $seeds[] = $nameParent instanceof PublicKey ? $nameParent : PublicKey::generate();

        $programIdPublicKey = PublicKey::from($this->config['NAME_PROGRAM_ID']);
        [$nameAccountKey] = PublicKey::findProgramAddressSync(
            seeds: $seeds,
            programId: $programIdPublicKey,
        );

        return $nameAccountKey;
    }

    /**
     * This function can be used to perform a reverse look up
     * @param connection The Solana RPC connection
     * @param nameAccount The public key of the domain to look up
     * @return string The human-readable domain name
     */
    public function reverseLookup(Connection $connection, PublicKey $nameAccount): string
    {
        $hashedReverseLookup = $this->getHashedNameSync($nameAccount->toBase58());
        $reverseLookupAccount = $this->getNameAccountKeySync($hashedReverseLookup, $this->config->REVERSE_LOOKUP_CLASS);

        $registry = NameRegistryStateAccount::retrieve($connection, $reverseLookupAccount);
        if (! $registry['data']) {
            throw new SNSError(SNSError::NoAccountData);
        }

        return $this->deserializeReverse($registry['data']);
    }

    public function deserializeReverse(
        $data
    ): ?string {
        if (! $data) {
            return null;
        }

        $nameLength = unpack('V', substr((string) $data, 0, 4))[1];

        return substr((string) $data, 4, $nameLength);
    }

    /**
     * This function can be used to compute the public key of a domain or subdomain
     * @param string $domain The domain to compute the public key for (e.g `bonfida.sol`, `dex.bonfida.sol`)
     * @param string|null $record Optional parameter: If the domain being resolved is a record
     * @return array{
     *     pubkey: PublicKey,
     *     hashed: Buffer,
     *     isSub: bool,
     *     parent: PublicKey|null,
     *     isSubRecord: bool,
     * }
     */
    public function getDomainKeySync(string $domain, ?string $record = null): array
    {
        if (str_ends_with($domain, '.sol')) {
            $domain = substr($domain, 0, -4);
        }

        $recordClass = $record === 'V2'
            ? $this->centralStateSNSRecords
            : null;

        $splitted = explode('.', $domain);
        if (count($splitted) === 2) {
            $prefix = $record ?: "\x00";
            $parentKey = $this->_deriveSync($splitted[1])['pubkey'];
            $result = $this->_deriveSync(
                name: $prefix . $splitted[0],
                parent: $parentKey,
                classKey: $recordClass,
            );

            return array_merge($result, ['isSub' => true, 'parent' => $parentKey]);
        }

        if (count($splitted) === 3 && $record) {
            // Parent key
            $parentKey = $this->_deriveSync($splitted[2])['pubkey'];

            // Sub domain
            $subKey = $this->_deriveSync(
                name: "\0" . $splitted[1],
                parent: $parentKey,
            )['pubkey'];

            // Sub record
            $recordPrefix = $record === 'V2' ? "\x02" : "\x01";

            $result = $this->_deriveSync(
                name: $recordPrefix . $splitted[0],
                parent: $subKey,
                classKey: PublicKey::from($recordClass),
            );

            return array_merge($result, ['isSub' => true, 'parent' => $parentKey, 'isSubRecord' => true]);
        }

        if (count($splitted) >= 3) {
            throw new SNSError(SNSError::InvalidInput);
        }

        $result = $this->_deriveSync(
            name: $domain,
            parent: PublicKey::from($this->config['ROOT_DOMAIN_ACCOUNT']),
        );

        return array_merge($result, ['isSub' => false, 'parent' => null]);
    }

    /**
     * @return array{pubkey: PublicKey, hashed: Buffer}
     */
    public function _deriveSync(string $name, ?PublicKey $parent = null, ?PublicKey $classKey = null): array
    {
        $hashedDomainName = $this->getHashedNameSync($name);

        return [
            'pubkey' => $this->getNameAccountKeySync(
                hashed_name: $hashedDomainName,
                nameClass: $classKey,
                nameParent: $parent ?: PublicKey::from($this->config['ROOT_DOMAIN_ACCOUNT']),
            ),
            'hashed' => $hashedDomainName,
        ];
    }

    /**
     * This function can be used to get the key of the reverse account
     *
     * @param string $domain The domain to compute the reverse for
     * @param bool|null $isSub Whether the domain is a subdomain or not
     * @return PublicKey The public key of the reverse account
     * @throws Exception
     * @throws SNSError
     * @throws InputValidationException
     */
    public function getReverseKeySync(string $domain, ?bool $isSub = null): PublicKey
    {
        $domainKeySync = $this->getDomainKeySync($domain);
        $pubkey = $domainKeySync['pubkey'];
        $hashedReverseLookup = $this->getHashedNameSync($pubkey->toBase58());

        return $this->getNameAccountKeySync(
            hashed_name: $hashedReverseLookup,
            nameClass: PublicKey::from($this->config['REVERSE_LOOKUP_CLASS']),
            nameParent: $isSub ? $domainKeySync['parent'] : null,
        );
    }

    /**
     * @throws SNSError
     * @throws AccountNotFoundException
     */
    public function getNameOwner(Connection $connection, string $parentNameKey): array
    {
        return NameRegistryStateAccount::retrieve($connection, $parentNameKey);

    }
}
