<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Programs\SNS;

use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Enum\Buffer\BufferType;
use Collectiq\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Collectiq\SolanaPhpSdk\Exceptions\SNSError;
use Collectiq\SolanaPhpSdk\Programs\SNS\State\NameRegistryStateAccount;
use Collectiq\SolanaPhpSdk\Programs\SNS\State\ReverseInstructionAccount;
use Collectiq\SolanaPhpSdk\Programs\SystemProgram;
use Collectiq\SolanaPhpSdk\PublicKey;
use Collectiq\SolanaPhpSdk\TransactionInstruction;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Exception;

/**
 * @method createInstruction($NAME_PROGRAM_ID, $programId, PublicKey $nameAccountKey, PublicKey $nameOwner, PublicKey $payerKey, Buffer $hashed_name, Buffer $param, Buffer $param1, PublicKey|null $nameClass, PublicKey|null $parentName, $nameParentOwner)
 * @method getReverseKeySync(string $subdomain, true $true)

 * @method getNameOwner(Connection $connection, PublicKey $parentName)
 * @method retrieve(Connection $connection, mixed $parent)
 * @method transferInstruction(mixed $NAME_PROGRAM_ID, mixed $pubkey, PublicKey $newOwner, PublicKey|null $owner, $null, mixed $nameParent, $nameParentOwner)
 */
trait Bindings
{
//    use Utils;
//    use Instructions;

    public function createSubdomain(
        Connection $connection,
        string $subdomain,
        PublicKey $owner,
        int $space = 2000,
        ?PublicKey $feePayer = null
    ): array {
        $ixs = [];
        $sub = explode('.', $subdomain)[0];
        if ($sub === '' || $sub === '0') {
            throw new SNSError(SNSError::InvalidSubdomain);
        }

        $domainKeySync = $this->getDomainKeySync($subdomain);
        $parent = $domainKeySync['parent'];
        $pubkey = $domainKeySync['pubkey'];

        $lamports = $connection->getMinimumBalanceForRentExemption(
            $space + NameRegistryStateAccount::SOL_RECORD_SIG_LEN
        );

        $ix_create = $this->createNameRegistry(
            connection: $connection,
            name: "\0" . $sub,
            space: $space,
            payerKey: $feePayer ?? $owner,
            nameOwner: $owner,
            lamports: $lamports,
            parentName: $parent,
        );
        $ixs[] = $ix_create;

        $reverseKey = $this->getReverseKeySync($subdomain, true);
        $info = $connection->getAccountInfo($reverseKey);
        if (! $info['data']) {
            $reverseName = $this->createReverseName(
                nameAccount: $pubkey,
                name: "\0" . $sub,
                feePayer: $feePayer ?? $owner,
                parentName: $parent,
                parentNameOwner: $owner,
            );
            $ixs = array_merge($ixs, $reverseName[1]);
        }

        return [[], $ixs];
    }

    /**
     * @throws SNSError
     * @throws AccountNotFoundException
     * @throws Exception
     */
    public function createSubdomainFast(
        Connection $connection,
        string $subdomain,
        PublicKey $subdomainPk,
        PublicKey $parentPk,
        PublicKey $owner,
        int $space = 1000,
        ?PublicKey $feePayer = null
    ): array {
        $ixs = [];
        $sub = explode('.', $subdomain)[0];
        if ($sub === '' || $sub === '0') {
            throw new SNSError(SNSError::InvalidSubdomain);
        }

//        $domainKeySync = $this->getDomainKeySync($subdomain);
//        $parent = $domainKeySync['parent'];
//        $pubkey = $domainKeySync['pubkey'];

        $lamports = (int) (0.01 * 10 ** 9); // 0.01 SOL

        $ix_create = $this->createNameRegistry(
            connection: $connection,
            name: "\0" . $sub,
            space: $space + NameRegistryStateAccount::SOL_RECORD_SIG_LEN,
            payerKey: $feePayer ?? $owner,
            nameOwner: $owner,
            lamports: $lamports,
            parentName: $parentPk
        );
        $ixs[] = $ix_create;

        // $reverseKey = $this->getReverseKeySync($subdomain, true);
       // $info = $connection->getAccountInfo($reverseKey);
        // if (!$info['data']) {
            $reverseName = $this->createReverseName(
                $subdomainPk,
                "\0" . $sub,
                $feePayer ?? $owner,
                $parentPk,
                $owner
            );
            $ixs = array_merge($ixs, $reverseName[1]);
       // }

        return [[], $ixs];
    }

    /**
     * Creates a name account with the given rent budget, allocated space, owner and class.
     *
     * @param Connection $connection The solana connection object to the RPC node
     * @param string $name The name of the new account
     * @param int $space The space in bytes allocated to the account
     * @param PublicKey $payerKey The allocation cost payer
     * @param PublicKey $nameOwner The pubkey to be set as owner of the new name account
     * @param int|null $lamports The budget to be set for the name account. If not specified, it'll be the minimum for rent exemption
     * @param PublicKey|null $nameClass The class of this new name
     * @param PublicKey|null $parentName The parent name of the new name. If specified its owner needs to sign
     * @throws Exception
     */
    public function createNameRegistry(
        Connection $connection,
        string $name,
        int $space,
        PublicKey $payerKey,
        PublicKey $nameOwner,
        ?int $lamports = null,
        ?PublicKey $nameClass = null,
        ?PublicKey $parentName = null
    ): TransactionInstruction {
        $hashed_name = $this->getHashedNameSync($name);
        $nameAccountKey = $this->getNameAccountKeySync($hashed_name, $nameClass, $parentName);

        $balance = $lamports ?: $connection->getMinimumBalanceForRentExemption($space);

        $nameParentOwner = $parentName;
        if ($parentName instanceof PublicKey) { // TODO review logic
            $parentAccount = $this->getNameOwner($connection, $parentName->toBase58());
            $nameParentOwner = $parentAccount['registry']->owner;
        }

        return $this->createInstruction(
            NAME_PROGRAM_ID: PublicKey::from($this->config['NAME_PROGRAM_ID']),
            programId: SystemProgram::programId(),
            nameAccountKey: $nameAccountKey,
            nameOwner: $nameOwner,
            payerKey: $payerKey,
            hashed_name: $hashed_name,
            param: Buffer::fromInt($balance, BufferType::LONG, false),
            param1: Buffer::fromInt($space, BufferType::INT, false),
            nameClass: $nameClass,
            parentName: $parentName,
            nameParentOwner: $nameParentOwner,
        );
    }

    /**
     * This function is used to transfer the ownership of a subdomain in the Solana Name Service.
     *
     * @param Connection $connection The Solana RPC connection object.
     * @param string $subdomain The subdomain to transfer. It can be with or without .sol suffix (e.g., 'something.bonfida.sol' or 'something.bonfida').
     * @param PublicKey $newOwner The public key of the new owner of the subdomain.
     * @param bool $isParentOwnerSigner A flag indicating whether the parent name owner is signing this transfer.
     * @param PublicKey|null $owner The public key of the current owner of the subdomain. This is an optional parameter. If not provided, the owner will be resolved automatically. This can be helpful to build transactions when the subdomain does not exist yet.
     *
     * @throws Exception
     */
    public function transferSubdomain(
        Connection $connection,
        string $subdomain,
        PublicKey $newOwner,
        bool $isParentOwnerSigner = false,
        ?PublicKey $owner = null
    ): TransactionInstruction {
        $domainKeySync = $this->getDomainKeySync($subdomain);
        $pubkey = $domainKeySync['pubkey'];
        $isSub = $domainKeySync['isSub'];
        $parent = $domainKeySync['parent'];

        if (! $parent || ! $isSub) {
            throw new SNSError(SNSError::InvalidSubdomain);
        }

        if (! $owner instanceof PublicKey) {
            $registry = $this->retrieve($connection, $pubkey);
            $owner = $registry['registry']->owner;
        }

        $nameParent = null;
        $nameParentOwner = null;

        if ($isParentOwnerSigner) {
            $nameParent = $parent;
            $parentAccount = $this->retrieve($connection, $parent);
            $nameParentOwner = $parentAccount['registry']->owner;
        }

        return $this->transferInstruction(
            NAME_PROGRAM_ID: $this->config['NAME_PROGRAM_ID'],
            pubkey: $pubkey,
            newOwner: $newOwner,
            owner: $owner,
            null: null,
            nameParent: $nameParent,
            nameParentOwner: $nameParentOwner,
        );
    }

    /**
     * This function is used to create a reverse name.
     *
     * @param PublicKey $nameAccount The name account to create the reverse account for
     * @param string $name The name of the domain
     * @param PublicKey $feePayer The fee payer of the transaction
     * @param PublicKey|null $parentName The parent name account
     * @param PublicKey|null $parentNameOwner The parent name owner
     * @throws Exception
     */
    public function createReverseName(
        PublicKey $nameAccount,
        string $name,
        PublicKey $feePayer,
        ?PublicKey $parentName = null,
        ?PublicKey $parentNameOwner = null
    ): array {
//        $centralState = $this->findProgramAddress(
//            [$this->config['REGISTER_PROGRAM_ID']->toBuffer()],
//            $this->config['REGISTER_PROGRAM_ID']
//        )[0];

        $hashedReverseLookup = $this->getHashedNameSync($nameAccount->toBase58());
        $reverseLookupAccount = $this->getNameAccountKeySync(
            $hashedReverseLookup,
            $this->centralStateSNSRecords,
            $parentName
        );

        $initCentralStateInstruction = new ReverseInstructionAccount($name);
        $initCentralStateInstruction->getInstruction(
            programId: PublicKey::from($this->config['REGISTER_PROGRAM_ID']),
            namingServiceProgram: PublicKey::from($this->config['NAME_PROGRAM_ID']),
            rootDomain: PublicKey::from($this->config['ROOT_DOMAIN_ACCOUNT']),
            reverseLookup: $reverseLookupAccount,
            systemProgram: SystemProgram::programId(),
            centralState: $this->centralStateSNSRecords,
            feePayer: $feePayer,
            rentSysvar: PublicKey::from($this->config['SYSVAR_RENT_PUBKEY']),
            parentName: $parentName,
            parentNameOwner: $parentNameOwner,
        );

        $instructions = [$initCentralStateInstruction];

        return [[], $instructions];
    }
}
