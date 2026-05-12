<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Tests\Unit;

use Collectiq\SolanaPhpSdk\Exceptions\MethodNotFoundException;
use Collectiq\SolanaPhpSdk\Services\SolanaRpcClient;
use Collectiq\SolanaPhpSdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SolanaRpcClientTest extends TestCase
{
    #[Test]
    public function generates_random_key(): void
    {
        $client = $this->assembleClient('POST', ['error' => [
            'code' => SolanaRpcClient::ERROR_CODE_METHOD_NOT_FOUND,
            'message' => 'ANYTHING',
        ]]);

        $rpc1 = $client->buildRpc('doStuff', []);
        $rpc2 = $client->buildRpc('doStuff', []);

        $client = $this->assembleClient('POST', ['result' => [
            'data' => 'SOMEDATABASE64ORJSON',
        ]]);

        $rpc3 = $client->buildRpc('doStuff', []);
        $rpc4 = $client->buildRpc('doStuff', []);

        self::assertSame($rpc1['id'], $rpc2['id']);
        self::assertSame($rpc3['id'], $rpc4['id']);
        self::assertNotSame($rpc1['id'], $rpc4['id']);
    }

    #[Test]
    public function throws_exception_for_invalid_methods(): void
    {
        $client = $this->assembleClient('POST', ['error' => [
            'code' => SolanaRpcClient::ERROR_CODE_METHOD_NOT_FOUND,
            'message' => 'Method not found',
        ]]);

        //        $solana = new SystemProgram($client);
        //
        //        $this->expectException(AccountNotFoundException::class);
        //        $solana->getAccountInfo('abc123');

        // Create mock handler for Guzzle
        //        $mockHandler = new MockHandler([
        //            new Response(
        //                200,
        //                [],
        //                json_encode([
        //                    'jsonrpc' => '2.0',
        //                    'error' => [
        //                        'code' => SolanaRpcClient::ERROR_CODE_METHOD_NOT_FOUND,
        //                        'message' => 'Method not found'
        //                    ],
        //                    'id' => 1
        //                ])
        //            ),
        //        ]);

        // $client = $this->assembleClient($mockHandler);

        // Assert that the correct exception is thrown for an invalid method
        $this->expectException(MethodNotFoundException::class);
        $client->call('invalid_method');
    }
}
