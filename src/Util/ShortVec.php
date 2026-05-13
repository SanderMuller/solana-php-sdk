<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Util;

final class ShortVec
{
    /**
     * @param array<int, int>|Buffer $buffer
     * @return array{0: int, 1: int} `[length, sizeOfPrefix]`
     */
    public static function decodeLength(array|Buffer $buffer): array
    {
        $buffer = Buffer::from($buffer)->toArray();

        $len = 0;
        $size = 0;
        while ($size < count($buffer)) {
            $elem = $buffer[$size];
            $len |= ($elem & 0x7F) << ($size * 7);
            $size++;
            if (($elem & 0x80) === 0) {
                break;
            }
        }

        return [$len, $size];
    }

    /**
     * @return array<int, int>
     */
    public static function encodeLength(int $length): array
    {
        $elems = [];
        $rem_len = $length;

        for (; ;) {
            $elem = $rem_len & 0x7F;
            $rem_len >>= 7;
            if ($rem_len === 0) {
                $elems[] = $elem;
                break;
            }

            $elem |= 0x80;
            $elems[] = $elem;
        }

        return $elems;
    }
}
