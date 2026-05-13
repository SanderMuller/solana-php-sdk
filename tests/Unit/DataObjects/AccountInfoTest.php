<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\DataObjects;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\DataObjects\AccountInfo;
use SanderMuller\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;

final class AccountInfoTest extends TestCase
{
    #[Test]
    public function from_value_decodes_base64_data_to_buffer(): void
    {
        $payload = base64_encode("\x01\x02\x03\xFF");

        $info = AccountInfo::fromValue([
            'lamports' => 5_000_000,
            'owner' => '11111111111111111111111111111111',
            'executable' => false,
            'rentEpoch' => 42,
            'space' => 4,
            'data' => [$payload, 'base64'],
        ]);

        self::assertSame(5_000_000, $info->lamports);
        self::assertSame('11111111111111111111111111111111', $info->owner->toBase58());
        self::assertFalse($info->executable);
        self::assertSame(42, $info->rentEpoch);
        self::assertSame(4, $info->space);
        self::assertSame('base64', $info->dataEncoding);
        self::assertSame([1, 2, 3, 255], $info->data?->toArray());
    }

    #[Test]
    public function from_value_decompresses_base64_zstd_data(): void
    {
        if (! function_exists('zstd_compress') || ! function_exists('zstd_uncompress')) {
            self::markTestSkipped('ext-zstd not available in this environment.');
        }

        $raw = "\xDE\xAD\xBE\xEF\x00\x01\x02\x03";
        $compressed = zstd_compress($raw);
        self::assertNotFalse($compressed);
        $payload = base64_encode($compressed);

        $info = AccountInfo::fromValue([
            'lamports' => 1,
            'owner' => '11111111111111111111111111111111',
            'executable' => false,
            'rentEpoch' => 0,
            'data' => [$payload, 'base64+zstd'],
        ]);

        self::assertSame('base64+zstd', $info->dataEncoding);
        self::assertSame($raw, $info->data?->toString());
    }

    #[Test]
    public function from_value_preserves_json_parsed_payload(): void
    {
        $info = AccountInfo::fromValue([
            'lamports' => 1,
            'owner' => '11111111111111111111111111111111',
            'executable' => false,
            'rentEpoch' => 0,
            'data' => ['parsed' => ['info' => ['mint' => 'X']], 'program' => 'spl-token'],
        ]);

        self::assertNull($info->data);
        self::assertSame('jsonParsed', $info->dataEncoding);
        self::assertIsArray($info->rawData);
        self::assertSame('spl-token', $info->rawData['program'] ?? null);
    }

    #[Test]
    public function array_access_mirrors_legacy_shape(): void
    {
        $payload = base64_encode('hi');
        $info = AccountInfo::fromValue([
            'lamports' => 1, 'owner' => '11111111111111111111111111111111',
            'executable' => false, 'rentEpoch' => 0,
            'data' => [$payload, 'base64'],
        ]);

        // Tests the ArrayAccess BC path — phpstan rule disabled inline so the
        // legacy-shape contract is still asserted under static analysis.
        // @phpstan-ignore-next-line typePerfect.noArrayAccessOnObject
        self::assertSame(1, $info['lamports']);
        // @phpstan-ignore-next-line typePerfect.noArrayAccessOnObject
        self::assertSame('11111111111111111111111111111111', $info['owner']);
        // @phpstan-ignore-next-line typePerfect.noArrayAccessOnObject
        self::assertFalse($info['executable']);
        // @phpstan-ignore-next-line typePerfect.noArrayAccessOnObject
        self::assertSame([$payload, 'base64'], $info['data']);
    }

    #[Test]
    public function connection_typed_accessor_returns_dto(): void
    {
        $payload = base64_encode('hi');

        $this->fakeRpcByMethod([
            'getAccountInfo' => [
                'context' => ['slot' => 1],
                'value' => [
                    'lamports' => 10,
                    'owner' => '11111111111111111111111111111111',
                    'executable' => false,
                    'rentEpoch' => 1,
                    'data' => [$payload, 'base64'],
                ],
            ],
        ]);

        $info = $this->container->get(Connection::class)->accountInfo('11111111111111111111111111111111');

        self::assertInstanceOf(AccountInfo::class, $info);
        self::assertSame(10, $info->lamports);
        self::assertSame('hi', $info->data?->toString());
    }

    #[Test]
    public function connection_typed_accessor_throws_when_account_missing(): void
    {
        $this->fakeRpcByMethod([
            'getAccountInfo' => [
                'context' => ['slot' => 1],
                'value' => null,
            ],
        ]);

        $this->expectException(AccountNotFoundException::class);

        $this->container->get(Connection::class)->accountInfo('11111111111111111111111111111111');
    }

    #[Test]
    public function multiple_accounts_typed_accessor_returns_null_for_gaps(): void
    {
        $payload = base64_encode('hi');

        $this->fakeRpcByMethod([
            'getMultipleAccounts' => [
                'context' => ['slot' => 1],
                'value' => [
                    null,
                    [
                        'lamports' => 7, 'owner' => '11111111111111111111111111111111',
                        'executable' => false, 'rentEpoch' => 0,
                        'data' => [$payload, 'base64'],
                    ],
                ],
            ],
        ]);

        $rows = $this->container->get(Connection::class)->multipleAccounts([
            '11111111111111111111111111111111', '11111111111111111111111111111111',
        ]);

        self::assertNull($rows[0]);
        self::assertInstanceOf(AccountInfo::class, $rows[1]);
        self::assertSame(7, $rows[1]->lamports);
    }
}
