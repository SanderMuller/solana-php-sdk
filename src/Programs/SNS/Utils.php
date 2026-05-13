<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Programs\SNS;

use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Exceptions\SNSError;
use SanderMuller\SolanaPhpSdk\Programs\SNS\State\NameRegistryStateAccount;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

trait Utils
{
    /**
     * SNS configuration map loaded from `Constants/config.json`.
     * `loadConstants()` validates that the six required keys exist as strings.
     *
     * @var array{
     *     NAME_PROGRAM_ID: string,
     *     REGISTER_PROGRAM_ID: string,
     *     ROOT_DOMAIN_ACCOUNT: string,
     *     REVERSE_LOOKUP_CLASS: string,
     *     SYSVAR_RENT_PUBKEY: string,
     *     HASH_PREFIX: string,
     * }
     */
    public array $config;

    /**
     * @return array{
     *     NAME_PROGRAM_ID: string,
     *     REGISTER_PROGRAM_ID: string,
     *     ROOT_DOMAIN_ACCOUNT: string,
     *     REVERSE_LOOKUP_CLASS: string,
     *     SYSVAR_RENT_PUBKEY: string,
     *     HASH_PREFIX: string,
     * }
     */
    private function loadConstants(): array
    {
        $jsonFilePath = dirname(__DIR__) . '/SNS/Constants/config.json';
        $raw = file_get_contents($jsonFilePath);

        if ($raw === false) {
            throw new InputValidationException("SNS config not readable at {$jsonFilePath}");
        }

        $decoded = json_decode($raw, true);

        $required = [
            'NAME_PROGRAM_ID', 'REGISTER_PROGRAM_ID', 'ROOT_DOMAIN_ACCOUNT',
            'REVERSE_LOOKUP_CLASS', 'SYSVAR_RENT_PUBKEY', 'HASH_PREFIX',
        ];

        if (! is_array($decoded)) {
            throw new InputValidationException('SNS config JSON malformed.');
        }

        foreach ($required as $key) {
            if (! isset($decoded[$key]) || ! is_string($decoded[$key])) {
                throw new InputValidationException("SNS config missing string key `{$key}`.");
            }
        }

        /** @var array{NAME_PROGRAM_ID: string, REGISTER_PROGRAM_ID: string, ROOT_DOMAIN_ACCOUNT: string, REVERSE_LOOKUP_CLASS: string, SYSVAR_RENT_PUBKEY: string, HASH_PREFIX: string} $decoded */
        return $decoded;
    }

    public function getHashedNameSync(string $name): Buffer
    {
        $prefix = $this->config['HASH_PREFIX'] ?? '';
        $input = (is_string($prefix) ? $prefix : '') . $name;

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

        $programIdPublicKey = PublicKey::from((string) $this->config['NAME_PROGRAM_ID']);
        [$nameAccountKey] = PublicKey::findProgramAddressSync(
            seeds: $seeds,
            programId: $programIdPublicKey,
        );

        return $nameAccountKey;
    }

    /**
     * Perform a reverse lookup: resolve the human-readable name registered
     * against $nameAccount.
     *
     * @throws SNSError When the reverse-lookup account has no data payload.
     * @throws AccountNotFoundException
     */
    public function reverseLookup(Connection $connection, PublicKey $nameAccount): string
    {
        $hashedReverseLookup = $this->getHashedNameSync($nameAccount->toBase58());
        $reverseLookupAccount = $this->getNameAccountKeySync(
            $hashedReverseLookup,
            PublicKey::from((string) $this->config['REVERSE_LOOKUP_CLASS']),
        );

        $registry = NameRegistryStateAccount::retrieve($connection, $reverseLookupAccount);
        $data = $registry['registry']->data ?? null;

        if (! $data instanceof Buffer || $data->length() === 0) {
            throw new SNSError(SNSError::NoAccountData);
        }

        $decoded = $this->deserializeReverse($data->toString());

        if ($decoded === null) {
            throw new SNSError(SNSError::NoAccountData);
        }

        return $decoded;
    }

    public function deserializeReverse(mixed $data): ?string
    {
        if (! is_string($data) || $data === '') {
            return null;
        }

        $unpacked = unpack('V', substr($data, 0, 4));
        if ($unpacked === false || ! isset($unpacked[1]) || ! is_int($unpacked[1])) {
            return null;
        }

        return substr($data, 4, $unpacked[1]);
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
            $prefix = $record !== null && $record !== '' ? $record : "\x00";
            $parentKey = $this->_deriveSync($splitted[1])['pubkey'];
            $result = $this->_deriveSync(
                name: $prefix . $splitted[0],
                parent: $parentKey,
                classKey: $recordClass,
            );

            return [
                'pubkey' => $result['pubkey'],
                'hashed' => $result['hashed'],
                'isSub' => true,
                'parent' => $parentKey,
                'isSubRecord' => false,
            ];
        }

        if (count($splitted) === 3 && $record !== null && $record !== '') {
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
                classKey: $recordClass,
            );

            return [
                'pubkey' => $result['pubkey'],
                'hashed' => $result['hashed'],
                'isSub' => true,
                'parent' => $parentKey,
                'isSubRecord' => true,
            ];
        }

        if (count($splitted) >= 3) {
            throw new SNSError(SNSError::InvalidInput);
        }

        $result = $this->_deriveSync(
            name: $domain,
            parent: PublicKey::from((string) $this->config['ROOT_DOMAIN_ACCOUNT']),
        );

        return [
            'pubkey' => $result['pubkey'],
            'hashed' => $result['hashed'],
            'isSub' => false,
            'parent' => null,
            'isSubRecord' => false,
        ];
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
                nameParent: $parent ?? PublicKey::from((string) $this->config['ROOT_DOMAIN_ACCOUNT']),
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
            nameClass: PublicKey::from((string) $this->config['REVERSE_LOOKUP_CLASS']),
            nameParent: $isSub === true ? $domainKeySync['parent'] : null,
        );
    }

    /**
     * @return array{registry: NameRegistryStateAccount, nftOwner: bool, nameAccountKey: PublicKey}
     * @throws SNSError
     * @throws AccountNotFoundException
     */
    public function getNameOwner(Connection $connection, string $parentNameKey): array
    {
        return NameRegistryStateAccount::retrieve($connection, $parentNameKey);
    }
}
