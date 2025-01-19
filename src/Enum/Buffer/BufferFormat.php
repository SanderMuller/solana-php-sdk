<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Enum\Buffer;

enum BufferFormat: string
{
    case CHAR_SIGNED = 'c';
    case CHAR_UNSIGNED = 'C';
    case SHORT_16_SIGNED = 's';
    case SHORT_16_UNSIGNED = 'v';
    case LONG_32_SIGNED = 'l';
    case LONG_32_UNSIGNED = 'V';
    case LONG_LONG_64_SIGNED = 'q';
    case LONG_LONG_64_UNSIGNED = 'P';
    case FLOAT = 'e';
}
