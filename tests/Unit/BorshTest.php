<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Borsh\Borsh;
use Collectiq\SolanaPhpSdk\Borsh\BorshSerializable;
use Collectiq\SolanaPhpSdk\Borsh\IsBorshObject;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TestObject implements BorshSerializable
{
    use IsBorshObject;
}

class TestWithPrivateVariable implements BorshSerializable
{
    use IsBorshObject;

    private $m;

    public function setM($m): void
    {
    $this->m = $m;
    }

    public function getM()
    {
    return $this->m;
    }
}

class TestWithConstructorParameters implements BorshSerializable
{
    use IsBorshObject;

    public function __construct(private $m) {}

    public function getM()
    {
    return $this->m;
    }

    public static function borshConstructor(): static
    {
        return new static(null);
    }
}

final class BorshTest extends TestCase
{
    #[Test]
    public function serialize_object(): void
    {
        $value = new TestObject();
        $value->fields['x'] = 255;
        $value->fields['y'] = 20;
        $value->fields['z'] = '123';
        $value->fields['zz'] = 'a123';
        $value->fields['a'] = 12.987;
        $value->fields['b'] = -121;
        $value->fields['c'] = -20;
        $value->fields['q'] = [1, 2, 3];

        $schema = [
            TestObject::class => [
                'kind' => 'struct',
                'fields' => [
                    ['x', 'u8'],
                    ['y', 'u64'],
                    ['z', 'string'],
                    ['zz', 'string'],
                    ['a', 'f64'],
                    ['b', 'i32'],
                    ['c', 'i8'],
                    ['q', [3]],
                ],
            ],
        ];

        $buffer = Borsh::serialize($schema, $value);
        $newValue = Borsh::deserialize($schema, TestObject::class, $buffer);

        self::assertInstanceOf(TestObject::class, $newValue);
        self::assertEquals(255, $newValue->fields['x']);
        self::assertEquals(20, $newValue->fields['y']);
        self::assertEquals('a123', $newValue->fields['zz']);
        self::assertEquals('123', $newValue->fields['z']);
        self::assertEqualsWithDelta(12.987, $newValue->fields['a'], PHP_FLOAT_EPSILON);
        self::assertEquals(-121, $newValue->fields['b']);
        self::assertEquals(-20, $newValue->fields['c']);
        self::assertEquals([1, 2, 3], $newValue->fields['q']);
    }

    #[Test]
    public function iserialize_optional_field(): void
    {
        $schema = [
            TestObject::class => [
                'kind' => 'struct',
                'fields' => [
                    ['x', [
                        'kind' => 'option',
                        'type' => 'string',
                    ]],
                ],
            ],
        ];

        $value = new TestObject();
        $value->x = 'bacon';
        $buffer = Borsh::serialize($schema, $value);
        $newValue = Borsh::deserialize($schema, TestObject::class, $buffer);
        self::assertEquals('bacon', $newValue->x);

        $value = new TestObject();
        $value->fields['x'] = null;
        $buffer = Borsh::serialize($schema, $value);
        $newValue = Borsh::deserialize($schema, TestObject::class, $buffer);
        self::assertNull($newValue->fields['x']);
    }

    #[Test]
    public function iserialize_deserialize_fixed_array(): void
    {
        $schema = [
            TestObject::class => [
                'kind' => 'struct',
                'fields' => [
                    ['x', ['string', 2]],
                ],
            ],
        ];

        $value = new TestObject();
        $value->x = ['hello', 'world'];

        $buffer = Borsh::serialize($schema, $value);
        $newValue = Borsh::deserialize($schema, TestObject::class, $buffer);

        self::assertSame([5, 0, 0, 0, 104, 101, 108, 108, 111, 5, 0, 0, 0, 119, 111, 114, 108, 100], $buffer);
        // Note, asserts TRUE because of the magic getter __get()
        self::assertEquals(['hello', 'world'], $newValue->x);
    }

    #[Test]
    public function iserialize_deserialize_invisible_properties(): void
    {
        $value = new TestWithPrivateVariable();
        $value->setM(255);

        $schema = [
            TestWithPrivateVariable::class => [
                'kind' => 'struct',
                'fields' => [
                    ['m', 'u8'],
                ],
            ],
        ];

        $buffer = Borsh::serialize($schema, $value);
        $newValue = Borsh::deserialize($schema, TestWithPrivateVariable::class, $buffer);

        self::assertInstanceOf(TestWithPrivateVariable::class, $newValue);
        self::assertEquals(255, $newValue->getM());
    }

    #[Test]
    public function iserialize_deserialize_handles_constructor_with_parameters(): void
    {
        $value = new TestWithConstructorParameters(255);

        $schema = [
            TestWithConstructorParameters::class => [
                'kind' => 'struct',
                'fields' => [
                    ['m', 'u8'],
                ],
            ],
        ];

        $buffer = Borsh::serialize($schema, $value);
        $newValue = Borsh::deserialize($schema, TestWithConstructorParameters::class, $buffer);

        self::assertInstanceOf(TestWithConstructorParameters::class, $newValue);
        self::assertEquals(255, $newValue->getM());
    }
}
