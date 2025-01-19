<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Enum\Buffer;

enum BufferType: string
{
    case STRING = 'string';
    case BYTE = 'byte';
    case SHORT = 'short';
    case INT = 'int';
    case LONG = 'long';
    case FLOAT = 'float';
}
