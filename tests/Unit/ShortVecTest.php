<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Tests\TestCase;
use Collectiq\SolanaPhpSdk\Util\ShortVec;
use PHPUnit\Framework\Attributes\Test;

final class ShortVecTest extends TestCase
{
    #[Test]
    public function idecode_length(): void
    {
        $this->checkDecodedArray([], 0, 0);
        $this->checkDecodedArray([5], 1, 5);
        $this->checkDecodedArray([0x7F], 1, 0x7F);
        $this->checkDecodedArray([0x80, 0x01], 2, 0x80);
        $this->checkDecodedArray([0xFF, 0x01], 2, 0xFF);
        $this->checkDecodedArray([0x80, 0x02], 2, 0x100);
        $this->checkDecodedArray([0x80, 0x02], 2, 0x100);
        $this->checkDecodedArray([0xFF, 0xFF, 0x01], 3, 0x7FFF);
        $this->checkDecodedArray([0x80, 0x80, 0x80, 0x01], 4, 0x200000);
    }

    #[Test]
    public function iencode_length(): void
    {
        $array = [];
        $prevLength = 0;

        $expected = [0];
        $this->checkEncodedArray($array, 0, $prevLength, $expected);
        $prevLength += count($expected);

        $expected = [5];
        $this->checkEncodedArray($array, 5, $prevLength, $expected);
        $prevLength += count($expected);

        $expected = [0x7F];
        $this->checkEncodedArray($array, 0x7F, $prevLength, $expected);
        $prevLength += count($expected);

        $expected = [0x80, 0x01];
        $this->checkEncodedArray($array, 0x80, $prevLength, $expected);
        $prevLength += count($expected);

        $expected = [0xFF, 0x01];
        $this->checkEncodedArray($array, 0xFF, $prevLength, $expected);
        $prevLength += count($expected);

        $expected = [0x80, 0x02];
        $this->checkEncodedArray($array, 0x100, $prevLength, $expected);
        $prevLength += count($expected);

        $expected = [0xFF, 0xFF, 0x01];
        $this->checkEncodedArray($array, 0x7FFF, $prevLength, $expected);
        $prevLength += count($expected);

        $expected = [0x80, 0x80, 0x80, 0x01];
        $this->checkEncodedArray(
            $array,
            0x200000,
            $prevLength,
            $expected
        );
        $prevLength += count($expected);

        self::assertSame(16, $prevLength);
        self::assertCount($prevLength, $array);
    }

    /**
     * @param int[] $array
     */
    private function checkDecodedArray(array $array, int $expectedLength, int $expectedValue): void
    {
        [$value, $length] = ShortVec::decodeLength($array);
        self::assertSame($expectedValue, $value);
        self::assertSame($expectedLength, $length);
    }

    /**
     * @param int[] $expectedArray
     */
    private function checkEncodedArray(array &$array, int $length, int $prevLength, array $expectedArray): void
    {
        self::assertSame(count($array), $prevLength);
        $actual = ShortVec::encodeLength($length);
        array_push($array, ...$actual);
        self::assertSame(count($array), $prevLength + count($expectedArray));
        self::assertEquals($expectedArray, array_slice($array, -count($expectedArray)));
    }
}
