<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * Builds a {@see DecodedTransaction} from a `getTransaction` RPC
 * response (`encoding: 'json'`, `maxSupportedTransactionVersion: 0`).
 *
 * The decoder handles legacy + V0 transactions uniformly by merging
 * `meta.loadedAddresses` into the static account-key list in the order
 * the runtime uses (static + loaded writable + loaded readonly) before
 * resolving instruction-account indexes.
 *
 * `fromRpcResponse()` is fail-soft: missing optional fields default to
 * empty collections / null. The only required pieces are
 * `transaction.message.accountKeys` and `transaction.message.instructions`.
 * Anything else can be absent without crashing the decode.
 *
 * @api
 */
final class TransactionDecoder
{
    /**
     * @param array<string, mixed> $rpc the raw `getTransaction` result
     */
    public static function fromRpcResponse(array $rpc, ?IdlRegistry $registry = null): DecodedTransaction
    {
        /** @var array<string, mixed> $transaction */
        $transaction = is_array($rpc['transaction'] ?? null) ? $rpc['transaction'] : [];
        /** @var array<string, mixed> $message */
        $message = is_array($transaction['message'] ?? null) ? $transaction['message'] : [];
        /** @var array<string, mixed>|null $meta */
        $meta = is_array($rpc['meta'] ?? null) ? $rpc['meta'] : null;

        $signature = self::firstSignature($transaction);
        $accountKeys = self::stringList($message['accountKeys'] ?? null);
        $header = is_array($message['header'] ?? null) ? $message['header'] : [];

        [$loadedWritable, $loadedReadonly] = self::loadedAddresses($meta);

        $resolvedKeys = [...$accountKeys, ...$loadedWritable, ...$loadedReadonly];

        $roleByIndex = self::buildAccountRoleMap(
            staticCount: count($accountKeys),
            loadedWritableCount: count($loadedWritable),
            loadedReadonlyCount: count($loadedReadonly),
            numRequiredSignatures: self::asInt($header['numRequiredSignatures'] ?? 0),
            numReadonlySigned: self::asInt($header['numReadonlySignedAccounts'] ?? 0),
            numReadonlyUnsigned: self::asInt($header['numReadonlyUnsignedAccounts'] ?? 0),
        );

        $signers = [];
        $writableIndexes = [];
        foreach ($roleByIndex as $idx => $role) {
            if ($role['isSigner']) {
                $signers[] = $resolvedKeys[$idx] ?? '';
            }
            if ($role['isWritable']) {
                $writableIndexes[] = $idx;
            }
        }

        $innerByParent = self::indexInnerInstructions($meta);

        $rawInstructions = is_array($message['instructions'] ?? null) ? $message['instructions'] : [];
        $instructions = [];
        foreach ($rawInstructions as $i => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            /** @var array<string, mixed> $raw */
            $top = InstructionDecoder::decode($raw, $resolvedKeys, $roleByIndex, $i, (string) $i, $registry);

            $innerList = [];
            foreach ($innerByParent[$i] ?? [] as $j => $rawInner) {
                $innerList[] = InstructionDecoder::decode(
                    $rawInner,
                    $resolvedKeys,
                    $roleByIndex,
                    $i,
                    "{$i}.{$j}",
                    $registry,
                );
            }

            $instructions[] = new DecodedInstruction(
                index: $top->index,
                path: $top->path,
                stackHeight: $top->stackHeight,
                programId: $top->programId,
                data: $top->data,
                dataBase58: $top->dataBase58,
                accounts: $top->accounts,
                innerInstructions: $innerList,
                idlName: $top->idlName,
                idlArgs: $top->idlArgs,
            );
        }

        $logMessages = is_array($meta['logMessages'] ?? null) ? $meta['logMessages'] : [];
        $logs = LogDecoder::decode(self::stringList($logMessages));

        return new DecodedTransaction(
            source: DecodedTransactionSource::Rpc,
            signature: $signature,
            slot: self::nullableInt($rpc['slot'] ?? null),
            blockTime: self::nullableInt($rpc['blockTime'] ?? null),
            fee: $meta === null ? null : self::nullableInt($meta['fee'] ?? null),
            computeUnitsConsumed: $meta === null ? null : self::nullableInt($meta['computeUnitsConsumed'] ?? null),
            err: $meta['err'] ?? null,
            accountKeys: $resolvedKeys,
            signers: $signers,
            writableIndexes: $writableIndexes,
            loadedWritableAddresses: $loadedWritable,
            loadedReadonlyAddresses: $loadedReadonly,
            instructions: $instructions,
            logs: $logs,
            meta: $meta,
        );
    }

    /**
     * @param array<string, mixed> $transaction
     */
    private static function firstSignature(array $transaction): ?string
    {
        $signatures = $transaction['signatures'] ?? null;
        if (! is_array($signatures) || $signatures === []) {
            return null;
        }

        $first = $signatures[0];

        return is_string($first) ? $first : null;
    }

    /**
     * @param array<string, mixed>|null $meta
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function loadedAddresses(?array $meta): array
    {
        if ($meta === null) {
            return [[], []];
        }

        $loaded = is_array($meta['loadedAddresses'] ?? null) ? $meta['loadedAddresses'] : [];

        return [
            self::stringList($loaded['writable'] ?? null),
            self::stringList($loaded['readonly'] ?? null),
        ];
    }

    /**
     * @return array<int, array{isSigner: bool, isWritable: bool}>
     */
    private static function buildAccountRoleMap(
        int $staticCount,
        int $loadedWritableCount,
        int $loadedReadonlyCount,
        int $numRequiredSignatures,
        int $numReadonlySigned,
        int $numReadonlyUnsigned,
    ): array {
        $roles = [];

        $writableSignedEnd = max(0, $numRequiredSignatures - $numReadonlySigned);
        $writableUnsignedEnd = $staticCount - $numReadonlyUnsigned;

        for ($i = 0; $i < $staticCount; $i++) {
            $isSigner = $i < $numRequiredSignatures;
            $isWritable = $i < $writableSignedEnd || ($i >= $numRequiredSignatures && $i < $writableUnsignedEnd);
            $roles[$i] = ['isSigner' => $isSigner, 'isWritable' => $isWritable];
        }

        for ($i = 0; $i < $loadedWritableCount; $i++) {
            $roles[$staticCount + $i] = ['isSigner' => false, 'isWritable' => true];
        }

        $readonlyOffset = $staticCount + $loadedWritableCount;
        for ($i = 0; $i < $loadedReadonlyCount; $i++) {
            $roles[$readonlyOffset + $i] = ['isSigner' => false, 'isWritable' => false];
        }

        return $roles;
    }

    /**
     * @param array<string, mixed>|null $meta
     * @return array<int, list<array<string, mixed>>>
     */
    private static function indexInnerInstructions(?array $meta): array
    {
        if ($meta === null || ! is_array($meta['innerInstructions'] ?? null)) {
            return [];
        }

        $out = [];
        foreach ($meta['innerInstructions'] as $entry) {
            if (! is_array($entry) || ! is_int($entry['index'] ?? null)) {
                continue;
            }

            $innerList = [];
            foreach (is_array($entry['instructions'] ?? null) ? $entry['instructions'] : [] as $inner) {
                if (is_array($inner)) {
                    /** @var array<string, mixed> $inner */
                    $innerList[] = $inner;
                }
            }

            $out[$entry['index']] = $innerList;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            if (is_string($entry)) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    private static function asInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }
}
