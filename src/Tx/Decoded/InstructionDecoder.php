<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

use StephenHill\Base58;

/**
 * Builds a single {@see DecodedInstruction} from one entry of the
 * `transaction.message.instructions` (or a member of an
 * `meta.innerInstructions[].instructions`) array.
 *
 * IDL resolution is intentionally out of scope for this class — it
 * stays focused on bytes + account-role resolution. Phase 3 wires the
 * {@see IdlRegistry} into the calling {@see TransactionDecoder}.
 *
 * @internal
 */
final class InstructionDecoder
{
    /**
     * @param array<string, mixed>             $rawInstruction RPC entry: `{programIdIndex, accounts, data, stackHeight?}`
     * @param list<string>                     $resolvedKeys   post-ALT-expansion list of pubkeys
     * @param array<int, array{isSigner: bool, isWritable: bool}> $roleByIndex keyed by index into $resolvedKeys
     */
    public static function decode(
        array $rawInstruction,
        array $resolvedKeys,
        array $roleByIndex,
        int $topLevelIndex,
        string $path,
        ?IdlRegistry $registry = null,
    ): DecodedInstruction {
        $programIdIndex = is_int($rawInstruction['programIdIndex'] ?? null) ? $rawInstruction['programIdIndex'] : 0;
        $programId = $resolvedKeys[$programIdIndex] ?? '';

        $accountIndexes = [];
        if (is_array($rawInstruction['accounts'] ?? null)) {
            foreach ($rawInstruction['accounts'] as $i) {
                if (is_int($i)) {
                    $accountIndexes[] = $i;
                }
            }
        }

        $accounts = [];
        foreach ($accountIndexes as $idx) {
            $role = $roleByIndex[$idx] ?? ['isSigner' => false, 'isWritable' => false];
            $accounts[] = new DecodedAccountRef(
                index: $idx,
                pubkey: $resolvedKeys[$idx] ?? '',
                isSigner: $role['isSigner'],
                isWritable: $role['isWritable'],
            );
        }

        $dataBase58 = is_string($rawInstruction['data'] ?? null) ? $rawInstruction['data'] : '';
        $data = $dataBase58 === '' ? '' : (new Base58())->decode($dataBase58);

        $stackHeight = is_int($rawInstruction['stackHeight'] ?? null) ? $rawInstruction['stackHeight'] : null;

        $idlName = null;
        $idlArgs = null;
        $decoder = $registry?->forProgram($programId);
        if ($decoder !== null) {
            $decoded = $decoder->decode($data, $accounts);
            if ($decoded !== null) {
                $idlName = $decoded['name'];
                $idlArgs = $decoded['args'];

                if (isset($decoded['accountNames']) && is_array($decoded['accountNames'])) {
                    $accounts = self::attachIdlAccountNames($accounts, $decoded['accountNames']);
                }
            }
        }

        return new DecodedInstruction(
            index: $topLevelIndex,
            path: $path,
            stackHeight: $stackHeight,
            programId: $programId,
            data: $data,
            dataBase58: $dataBase58,
            accounts: $accounts,
            innerInstructions: [],
            idlName: $idlName,
            idlArgs: $idlArgs,
        );
    }

    /**
     * @param list<DecodedAccountRef> $accounts
     * @param list<?string>           $idlNames
     * @return list<DecodedAccountRef>
     */
    private static function attachIdlAccountNames(array $accounts, array $idlNames): array
    {
        $out = [];
        foreach ($accounts as $i => $account) {
            $name = $idlNames[$i] ?? null;
            $out[] = new DecodedAccountRef(
                index: $account->index,
                pubkey: $account->pubkey,
                isSigner: $account->isSigner,
                isWritable: $account->isWritable,
                idlName: is_string($name) ? $name : null,
            );
        }

        return $out;
    }
}
