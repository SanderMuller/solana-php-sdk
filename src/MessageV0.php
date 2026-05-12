<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Support\PublicKeyCollection;
use Collectiq\SolanaPhpSdk\Util\AccountMeta;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\CompiledInstruction;
use Collectiq\SolanaPhpSdk\Util\MessageAddressTableLookup;
use Collectiq\SolanaPhpSdk\Util\MessageHeader;
use Collectiq\SolanaPhpSdk\Util\ShortVec;

/**
 * Solana versioned (v0) message. Supports Address Lookup Tables.
 *
 * Wire format:
 *   [ prefix: 0x80 | version ]
 *   [ header: 3 bytes ]
 *   [ static_account_keys: shortvec-count + 32-byte pubkeys ]
 *   [ recent_blockhash: 32 bytes ]
 *   [ instructions: shortvec-count + compiled instructions ]
 *   [ address_table_lookups: shortvec-count + lookups ]
 */
final readonly class MessageV0 implements VersionedMessage
{
    public const int VERSION = 0;

    private const int VERSION_PREFIX_MASK = 0x80;

    public function __construct(
        public MessageHeader       $header,
        public PublicKeyCollection $staticAccountKeys,
        public string              $recentBlockhash,
        /** @var array<CompiledInstruction> */
        public array               $compiledInstructions,
        /** @var array<MessageAddressTableLookup> */
        public array               $addressTableLookups = [],
    ) {}

    /**
     * Compile a v0 message from instructions and (optionally) loaded address
     * lookup tables. Keys present in any of the supplied tables are moved out
     * of the static account list into the corresponding table lookup, in the
     * order required by the runtime (writable then readonly).
     *
     * @param array<TransactionInstruction>       $instructions
     * @param array<AddressLookupTableAccount>    $addressLookupTableAccounts
     */
    public static function compile(
        PublicKey $payerKey,
        array     $instructions,
        string    $recentBlockhash,
        array     $addressLookupTableAccounts = [],
    ): self {
        if ($instructions === []) {
            throw new InputValidationException('No instructions provided.');
        }

        $metas = self::collectAccountMetas($payerKey, $instructions);
        $metas = self::dedupeAndSort($metas, $payerKey);

        $tableLookups = [];
        $staticKeys = [];
        $writableLookupKeys = [];
        $readonlyLookupKeys = [];

        foreach ($metas as $meta) {
            $key = $meta->getPublicKey();

            // A key must stay static if it is a signer or if it is used as a
            // program id; otherwise it can be sourced from a lookup table.
            if ($meta->isSigner || self::isProgramId($key, $instructions)) {
                $staticKeys[] = $meta;

                continue;
            }

            $located = self::locateInLookup($key, $addressLookupTableAccounts);
            if ($located === null) {
                $staticKeys[] = $meta;

                continue;
            }

            [$tableKey, $index] = $located;
            $serializedTableKey = $tableKey->toBase58();

            if (! isset($tableLookups[$serializedTableKey])) {
                $tableLookups[$serializedTableKey] = [
                    'key' => $tableKey,
                    'writable' => [],
                    'readonly' => [],
                ];
            }

            if ($meta->isWritable) {
                $tableLookups[$serializedTableKey]['writable'][] = $index;
                $writableLookupKeys[] = $key;
            } else {
                $tableLookups[$serializedTableKey]['readonly'][] = $index;
                $readonlyLookupKeys[] = $key;
            }
        }

        $header = self::buildHeader($staticKeys);

        $staticAccountKeys = PublicKeyCollection::empty();
        foreach ($staticKeys as $meta) {
            $staticAccountKeys->add($meta->getPublicKey());
        }

        $accountKeyIndexes = self::buildAccountKeyIndex(
            $staticAccountKeys,
            $writableLookupKeys,
            $readonlyLookupKeys,
        );

        $compiledInstructions = array_map(
            static function (TransactionInstruction $ix) use ($accountKeyIndexes, $staticAccountKeys): CompiledInstruction {
                $programIdIndex = $staticAccountKeys->search(
                    fn (PublicKey $k): bool => $ix->programId->equals($k),
                    true,
                );

                if ($programIdIndex === false) {
                    throw new InputValidationException("Program id {$ix->programId->toBase58()} missing from static account keys.");
                }

                $accounts = array_map(
                    static function (AccountMeta $am) use ($accountKeyIndexes): int {
                        $idx = $accountKeyIndexes[$am->getPublicKey()->toBase58()] ?? null;

                        if ($idx === null) {
                            throw new InputValidationException("Account {$am->getPublicKey()->toBase58()} missing from compiled account keys.");
                        }

                        return $idx;
                    },
                    $ix->keys,
                );

                return new CompiledInstruction(
                    programIdIndex: $programIdIndex,
                    accounts: $accounts,
                    data: $ix->data,
                );
            },
            $instructions,
        );

        $lookups = array_map(
            static fn (array $entry): MessageAddressTableLookup => new MessageAddressTableLookup(
                accountKey: $entry['key'],
                writableIndexes: $entry['writable'],
                readonlyIndexes: $entry['readonly'],
            ),
            array_values($tableLookups),
        );

        return new self(
            header: $header,
            staticAccountKeys: $staticAccountKeys,
            recentBlockhash: $recentBlockhash,
            compiledInstructions: $compiledInstructions,
            addressTableLookups: $lookups,
        );
    }

    public function version(): int
    {
        return self::VERSION;
    }

    public function header(): MessageHeader
    {
        return $this->header;
    }

    public function staticAccountKeys(): PublicKeyCollection
    {
        return $this->staticAccountKeys;
    }

    public function recentBlockhash(): string
    {
        return $this->recentBlockhash;
    }

    /**
     * @return array<CompiledInstruction>
     */
    public function compiledInstructions(): array
    {
        return $this->compiledInstructions;
    }

    public function serialize(): string
    {
        $out = Buffer::empty();

        $out->push([self::VERSION_PREFIX_MASK | self::VERSION]);
        $out->push([
            $this->header->numRequiredSignature,
            $this->header->numReadonlySignedAccounts,
            $this->header->numReadonlyUnsignedAccounts,
        ]);

        $out->push(ShortVec::encodeLength($this->staticAccountKeys->count()));
        foreach ($this->staticAccountKeys as $key) {
            $out->push($key->toBytes());
        }

        $out->push(Buffer::fromBase58($this->recentBlockhash)->toArray());

        $out->push(ShortVec::encodeLength(count($this->compiledInstructions)));
        foreach ($this->compiledInstructions as $ix) {
            $out->push([$ix->programIdIndex]);
            $out->push(ShortVec::encodeLength(count($ix->accounts)));
            $out->push($ix->accounts);
            $out->push(ShortVec::encodeLength($ix->data->length()));
            $out->push($ix->data->toArray());
        }

        $out->push(ShortVec::encodeLength(count($this->addressTableLookups)));
        foreach ($this->addressTableLookups as $lookup) {
            $out->push($lookup->accountKey->toBytes());
            $out->push(ShortVec::encodeLength(count($lookup->writableIndexes)));
            $out->push($lookup->writableIndexes);
            $out->push(ShortVec::encodeLength(count($lookup->readonlyIndexes)));
            $out->push($lookup->readonlyIndexes);
        }

        return $out->toString();
    }

    /**
     * @param Buffer|array<int, int>|string $buffer
     */
    public static function deserialize(Buffer|array|string $buffer): self
    {
        if (is_string($buffer)) {
            $buffer = Buffer::fromString($buffer);
        }

        $buffer = Buffer::from($buffer);

        $prefix = $buffer->shift();
        if ($prefix === null || ($prefix & self::VERSION_PREFIX_MASK) === 0) {
            throw new InputValidationException('Buffer is not a versioned message (missing 0x80 prefix).');
        }

        $version = $prefix & ~self::VERSION_PREFIX_MASK;
        if ($version !== self::VERSION) {
            throw new InputValidationException("Unsupported message version: {$version}");
        }

        $numRequiredSignatures = $buffer->shift();
        $numReadonlySigned = $buffer->shift();
        $numReadonlyUnsigned = $buffer->shift();

        if ($numRequiredSignatures === null || $numReadonlySigned === null || $numReadonlyUnsigned === null) {
            throw new InputValidationException('Message header truncated.');
        }

        $header = new MessageHeader($numRequiredSignatures, $numReadonlySigned, $numReadonlyUnsigned);

        [$staticCount, $offset] = ShortVec::decodeLength($buffer);
        $buffer = $buffer->slice((int) $offset);
        $staticKeys = PublicKeyCollection::empty();
        for ($i = 0; $i < $staticCount; $i++) {
            $staticKeys->add(PublicKey::from($buffer->slice(0, PublicKey::$fixedLength)));
            $buffer = $buffer->slice(PublicKey::$fixedLength);
        }

        $recentBlockhash = $buffer->slice(0, PublicKey::$fixedLength)->toBase58String();
        $buffer = $buffer->slice(PublicKey::$fixedLength);

        [$ixCount, $offset] = ShortVec::decodeLength($buffer);
        $buffer = $buffer->slice((int) $offset);
        $instructions = [];
        for ($i = 0; $i < $ixCount; $i++) {
            $programIdIndex = (int) $buffer->shift();
            [$accLen, $offset] = ShortVec::decodeLength($buffer);
            $buffer = $buffer->slice((int) $offset);
            /** @var array<int, int> $accounts */
            $accounts = $buffer->slice(0, (int) $accLen)->toArray();
            $buffer = $buffer->slice((int) $accLen);
            [$dataLen, $offset] = ShortVec::decodeLength($buffer);
            $buffer = $buffer->slice((int) $offset);
            $data = $buffer->slice(0, (int) $dataLen);
            $buffer = $buffer->slice((int) $dataLen);

            $instructions[] = new CompiledInstruction(
                programIdIndex: $programIdIndex,
                accounts: $accounts,
                data: $data,
            );
        }

        [$lookupCount, $offset] = ShortVec::decodeLength($buffer);
        $buffer = $buffer->slice((int) $offset);
        $lookups = [];
        for ($i = 0; $i < $lookupCount; $i++) {
            $accountKey = PublicKey::from($buffer->slice(0, PublicKey::$fixedLength));
            $buffer = $buffer->slice(PublicKey::$fixedLength);

            [$wLen, $offset] = ShortVec::decodeLength($buffer);
            $buffer = $buffer->slice((int) $offset);
            /** @var array<int, int> $writable */
            $writable = $buffer->slice(0, (int) $wLen)->toArray();
            $buffer = $buffer->slice((int) $wLen);

            [$rLen, $offset] = ShortVec::decodeLength($buffer);
            $buffer = $buffer->slice((int) $offset);
            /** @var array<int, int> $readonly */
            $readonly = $buffer->slice(0, (int) $rLen)->toArray();
            $buffer = $buffer->slice((int) $rLen);

            $lookups[] = new MessageAddressTableLookup(
                accountKey: $accountKey,
                writableIndexes: $writable,
                readonlyIndexes: $readonly,
            );
        }

        return new self(
            header: $header,
            staticAccountKeys: $staticKeys,
            recentBlockhash: $recentBlockhash,
            compiledInstructions: $instructions,
            addressTableLookups: $lookups,
        );
    }

    /**
     * @param array<TransactionInstruction> $instructions
     * @return array<AccountMeta>
     */
    private static function collectAccountMetas(PublicKey $payerKey, array $instructions): array
    {
        $metas = [new AccountMeta($payerKey, true, true)];

        foreach ($instructions as $ix) {
            foreach ($ix->keys as $key) {
                $metas[] = $key;
            }

            // Program id participates as a readonly non-signer.
            $metas[] = new AccountMeta($ix->programId, false, false);
        }

        return $metas;
    }

    /**
     * @param  array<AccountMeta> $metas
     * @return array<AccountMeta>
     */
    private static function dedupeAndSort(array $metas, PublicKey $payerKey): array
    {
        /** @var array<string, AccountMeta> $unique */
        $unique = [];

        foreach ($metas as $meta) {
            $key = $meta->getPublicKey()->toBase58();

            if (! isset($unique[$key])) {
                $unique[$key] = new AccountMeta($meta->getPublicKey(), $meta->isSigner, $meta->isWritable);

                continue;
            }

            $unique[$key]->isSigner = $unique[$key]->isSigner || $meta->isSigner;
            $unique[$key]->isWritable = $unique[$key]->isWritable || $meta->isWritable;
        }

        $list = array_values($unique);

        usort($list, static function (AccountMeta $a, AccountMeta $b): int {
            if ($a->isSigner !== $b->isSigner) {
                return $a->isSigner ? -1 : 1;
            }

            if ($a->isWritable !== $b->isWritable) {
                return $a->isWritable ? -1 : 1;
            }

            return 0;
        });

        // Force fee payer to index 0 as a signer + writable.
        $payerString = $payerKey->toBase58();
        foreach ($list as $i => $meta) {
            if ($meta->getPublicKey()->toBase58() === $payerString) {
                if ($i !== 0) {
                    [$payerMeta] = array_splice($list, $i, 1);
                    array_unshift($list, $payerMeta);
                }

                $list[0]->isSigner = true;
                $list[0]->isWritable = true;
                break;
            }
        }

        return $list;
    }

    /**
     * @param array<TransactionInstruction> $instructions
     */
    private static function isProgramId(PublicKey $key, array $instructions): bool
    {
        foreach ($instructions as $ix) {
            if ($ix->programId->equals($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<AddressLookupTableAccount> $tables
     * @return array{0: PublicKey, 1: int}|null
     */
    private static function locateInLookup(PublicKey $key, array $tables): ?array
    {
        foreach ($tables as $table) {
            foreach ($table->addresses as $idx => $candidate) {
                if ($candidate->equals($key)) {
                    return [$table->key, $idx];
                }
            }
        }

        return null;
    }

    /**
     * @param array<AccountMeta> $staticMetas
     */
    private static function buildHeader(array $staticMetas): MessageHeader
    {
        $numRequiredSignatures = 0;
        $numReadonlySigned = 0;
        $numReadonlyUnsigned = 0;

        foreach ($staticMetas as $meta) {
            if ($meta->isSigner) {
                $numRequiredSignatures++;
                if (! $meta->isWritable) {
                    $numReadonlySigned++;
                }
            } elseif (! $meta->isWritable) {
                $numReadonlyUnsigned++;
            }
        }

        return new MessageHeader($numRequiredSignatures, $numReadonlySigned, $numReadonlyUnsigned);
    }

    /**
     * @param array<PublicKey> $writableLookupKeys
     * @param array<PublicKey> $readonlyLookupKeys
     * @return array<string, int>
     */
    private static function buildAccountKeyIndex(
        PublicKeyCollection $staticKeys,
        array               $writableLookupKeys,
        array               $readonlyLookupKeys,
    ): array {
        $index = [];
        $i = 0;

        foreach ($staticKeys as $key) {
            $index[$key->toBase58()] = $i++;
        }

        foreach ($writableLookupKeys as $key) {
            $index[$key->toBase58()] = $i++;
        }

        foreach ($readonlyLookupKeys as $key) {
            $index[$key->toBase58()] = $i++;
        }

        return $index;
    }
}
