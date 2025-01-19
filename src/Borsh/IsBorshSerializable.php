<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

use ReflectionClass;

trait IsBorshSerializable
{
    /**
     * Magic getter to retrieve dynamically set properties.
     * Note, changed from dynamic properties make use of an Array due to Dynamic properties being deprecated.
     *
     * @return mixed|null
     */
    public function __get(string $name): mixed
    {
        // Check if the property exists in the dynamic properties
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        }

        // Check if the property exists as a private property
        if ($this->isPrivateProperty($name)) {
            // Use reflection to access the private property
            $reflectionClass = new ReflectionClass($this);
            $property = $reflectionClass->getProperty($name);
            $property->setAccessible(true);

            return $property->getValue($this);
        }

        // Property not found
        return null;
    }
}
