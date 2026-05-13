<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Exceptions\MethodNotFoundException;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class SolanaRpcClientTest extends TestCase
{
    #[Test]
    public function generates_a_fresh_nonce_per_call(): void
    {
        $client = $this->assembleClient('POST', ['error' => [
            'code' => SolanaRpcClient::ERROR_CODE_METHOD_NOT_FOUND,
            'message' => 'ANYTHING',
        ]]);

        $rpc1 = $client->buildRpc('doStuff', []);
        $rpc2 = $client->buildRpc('doStuff', []);

        self::assertNotSame($rpc1['id'], $rpc2['id']);
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
