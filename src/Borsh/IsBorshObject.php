<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Borsh;

trait IsBorshObject
{
    use IsBorshDeserializable;
    use IsBorshSerializable;

    /**
     * @var array Holds dynamic properties
     */
    public $fields = [];
}
