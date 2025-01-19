<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit\Programs;

use Collectiq\SolanaPhpSdk\Programs\DidSolProgram;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DidSolProgramTest extends TestCase
{
    public const int ACC_DATA_SIZE = 158;

    public const string DID_ID = 'did:sol:devnet:3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk';

    public const string DID_SUBJECT_PK = '3Js7k6xYQbvXv6qUYLapYV7Sptfg37Tss9GcAyVEuUqk';

    public const string DID_ACCOUNT_ID = '2LA5JTs1cxFewfnXzVBpaFHpABBj1akR2aQzwDSovwCg';

    public const string DID_DATA = 'TVjvjfsd7fMA/gAAAAAAAAAABwAAAGRlZmF1bHRIAAAgAAAAIkrqC+g88eamANb3tU6OiBJW21IjBWP85MhI4XKkOscAAAAAAQAAAAUAAABhZ2VudAwAAABBZ2VudFNlcnZpY2UtAAAAaHR0cHM6Ly9hdHRlc3R0by1icmVlemUtdnVlLnRlc3QvLndlbGwta25vd24vAAAAAAAAAAA=';

    #[Test]
    public function ideserializes_diddata(): void
    {
        $base64Data = self::DID_DATA;
        $didData = DidSolProgram::deserializeDidData($base64Data);

        $this->assertEquals(self::DID_SUBJECT_PK, $didData->keyData);

    }

    #[Test]
    public function igets_did_data_account_id(): void
    {

        $didId = DidSolProgram::getDidDataAccountId(self::DID_SUBJECT_PK);
        $this->assertSame(self::DID_ACCOUNT_ID, $didId);

    }
}
