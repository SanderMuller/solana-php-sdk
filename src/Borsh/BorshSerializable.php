<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

interface BorshSerializable
{
    /**
     * @return $this
     */
    public static function borshConstructor(): static;

    public function __get(string $name): mixed;

    public function __set(string $name, mixed $value): void;

    public function __isset(string $name): bool;

    public function __unset(string $name): void;
}
