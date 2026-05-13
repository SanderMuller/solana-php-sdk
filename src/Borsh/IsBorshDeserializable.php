<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Borsh;

use ReflectionClass;
use ReflectionProperty;

trait IsBorshDeserializable
{
    /**
     * Backing store for dynamic Borsh-deserialised properties.
     *
     * Private properties declared on the host class are written directly via
     * reflection in {@see __set} and live as real class properties; everything
     * else is stored here.
     *
     * @var array<string, mixed>
     */
    public array $fields = [];

    /**
     * Create a new instance of this object.
     *
     * `new static()` is the documented extension point of this trait — every
     * Borsh-serialisable class is expected to expose a no-arg constructor (or
     * override `borshConstructor()`). PHPStan's "unsafe new static" check
     * doesn't model that contract, so we intentionally suppress it here.
     *
     * Note: must override when the default constructor takes required parameters!
     *
     * @phpstan-ignore-next-line new.static
     */
    public static function borshConstructor(): static
    {
        return new static();
    }

    public function __set(string $name, mixed $value): void
    {
        if ($this->isPrivateProperty($name)) {
            $this->reflectionPropertyFor($name)->setValue($this, $value);

            return;
        }

        $this->fields[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        if (array_key_exists($name, $this->fields)) {
            return true;
        }

        if (! $this->isPrivateProperty($name)) {
            return false;
        }

        return $this->reflectionPropertyFor($name)->isInitialized($this);
    }

    public function __unset(string $name): void
    {
        if (array_key_exists($name, $this->fields)) {
            unset($this->fields[$name]);

            return;
        }

        if (! $this->isPrivateProperty($name)) {
            return;
        }

        $this->reflectionPropertyFor($name)->setValue($this, null);
    }

    private function isPrivateProperty(string $name): bool
    {
        $reflectionClass = new ReflectionClass(static::class);

        return $reflectionClass->hasProperty($name)
            && $reflectionClass->getProperty($name)->isPrivate();
    }

    private function reflectionPropertyFor(string $name): ReflectionProperty
    {
        return new ReflectionClass(static::class)->getProperty($name);
    }
}
