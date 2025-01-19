<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

use ReflectionClass;

trait IsBorshDeserializable
{
    /**
     * Create a new instance of this object.
     *
     * Note: must override when the default constructor required parameters!
     *
     * @return $this
     */
    public static function borshConstructor(): static
    {
        return new static();
    }

    /**
     * Magic setter to dynamically set properties.
     */
    public function __set(string $name, mixed $value): void
    {
        // Set the value in the dynamic properties if it's not private
        if (! $this->isPrivateProperty($name)) {
            $this->fields[$name] = $value;
        }

        // Check if the property exists as a private property
        if ($this->isPrivateProperty($name)) {
            // Use reflection to set the value of the private property
            $reflectionClass = new ReflectionClass($this);
            $property = $reflectionClass->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($this, $value);
        }
    }

    public function __isset(string $name): bool
    {
        return isset($this->fields[$name]) || isset($this->private[$name]);
    }

    public function __unset(string $name): void
    {
        if (isset($this->fields[$name])) {
            unset($this->fields[$name]);
        } elseif (isset($this->private[$name])) {
            unset($this->privateProperties[$name]);
        }
    }

    private function isPrivateProperty(string $name): bool
    {
        // Get the class name ( whatever class is implementing this trait, e.g. Any Schema/Struct based object
        $className = static::class;

        // Create a ReflectionClass instance for the class
        $reflectionClass = new ReflectionClass($className);

        // Check if the property is declared in the class and is private
        return $reflectionClass->hasProperty($name) && $reflectionClass->getProperty($name)->isPrivate();
    }
}
