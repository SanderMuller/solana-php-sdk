<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Anchor;

use SanderMuller\SolanaPhpSdk\Borsh\BinaryReader;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\ProgramDecoder;
use SanderMuller\SolanaPhpSdk\Util\Buffer;
use Throwable;

/**
 * {@see ProgramDecoder} backed by an {@see AnchorIdl}.
 *
 * Reads the first 8 bytes of instruction data as the discriminator,
 * matches against the IDL's `instructions[*]->discriminator`, then
 * Borsh-decodes the remaining bytes via {@see IdlEncoder::decode()}.
 *
 * Returns null when the discriminator doesn't match any instruction
 * in the IDL — callers fall back to raw bytes. Borsh decode errors
 * also return null (with the swallow point documented) so one
 * malformed instruction can't poison a batch decode.
 *
 * @api
 */
final class AnchorProgramDecoder implements ProgramDecoder
{
    /** @var array<string, IdlInstruction> keyed by hex-encoded discriminator */
    private readonly array $byDiscriminator;

    public function __construct(public readonly AnchorIdl $idl)
    {
        $map = [];
        foreach ($idl->instructions as $ix) {
            $map[self::discriminatorKey($ix->discriminator)] = $ix;
        }
        $this->byDiscriminator = $map;
    }

    public function programId(): string
    {
        return $this->idl->programId;
    }

    public function decode(string $instructionData, array $accounts): ?array
    {
        if (strlen($instructionData) < 8) {
            return null;
        }

        $discriminatorBytes = substr($instructionData, 0, 8);
        $key = bin2hex($discriminatorBytes);
        $ix = $this->byDiscriminator[$key] ?? null;
        if ($ix === null) {
            return null;
        }

        $payload = substr($instructionData, 8);
        $reader = new BinaryReader(Buffer::from($payload));

        try {
            $args = IdlEncoder::decode($ix, $reader, $this->idl);
        } catch (Throwable) {
            // Spec §"Failure modes": Borsh decode error leaves $idlArgs null so
            // the surrounding decode keeps moving. The caller still sees the
            // instruction name + accounts.
            $args = null;
        }

        $accountNames = [];
        foreach ($ix->accounts as $i => $spec) {
            $accountNames[$i] = $spec->name;
        }

        $result = ['name' => $ix->name, 'args' => $args ?? []];
        if ($accountNames !== []) {
            $result['accountNames'] = array_values($accountNames);
        }

        /** @var array{name: string, args: array<string, mixed>, accountNames?: list<?string>} $result */
        return $result;
    }

    /**
     * @param list<int> $bytes
     */
    private static function discriminatorKey(array $bytes): string
    {
        $binary = '';
        foreach ($bytes as $byte) {
            $binary .= chr($byte);
        }

        return bin2hex($binary);
    }
}
