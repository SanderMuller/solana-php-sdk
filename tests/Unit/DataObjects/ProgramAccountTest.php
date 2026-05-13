<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\DataObjects;

use PHPUnit\Framework\Attributes\Test;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\DataObjects\GpaFilter;
use SanderMuller\SolanaPhpSdk\DataObjects\ProgramAccount;
use SanderMuller\SolanaPhpSdk\Exceptions\InputValidationException;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\PublicKey;
use SanderMuller\SolanaPhpSdk\Tests\TestCase;
use SanderMuller\SolanaPhpSdk\Util\Buffer;

final class ProgramAccountTest extends TestCase
{
    #[Test]
    public function from_row_decodes_pubkey_and_account_info(): void
    {
        $payload = base64_encode("\x01\x02\x03");

        $row = ProgramAccount::fromRow([
            'pubkey' => '11111111111111111111111111111111',
            'account' => [
                'lamports' => 7,
                'owner' => '11111111111111111111111111111111',
                'executable' => false,
                'rentEpoch' => 0,
                'data' => [$payload, 'base64'],
            ],
        ]);

        self::assertSame('11111111111111111111111111111111', $row->pubkey->toBase58());
        self::assertSame(7, $row->account->lamports);
        self::assertSame('11111111111111111111111111111111', $row->account->owner->toBase58());
        self::assertSame([1, 2, 3], $row->account->data?->toArray());
    }

    #[Test]
    public function memcmp_builder_handles_publickey_buffer_and_string_inputs(): void
    {
        $pk = Keypair::generate()->getPublicKey();

        $byPk = GpaFilter::memcmp(0, $pk);
        self::assertSame($pk->toBase58(), $byPk['memcmp']['bytes']);
        self::assertSame('base58', $byPk['memcmp']['encoding']);

        $buffer = Buffer::from([0xDE, 0xAD, 0xBE, 0xEF]);
        $byBufferBase58 = GpaFilter::memcmp(4, $buffer);
        self::assertSame('base58', $byBufferBase58['memcmp']['encoding']);

        $byBufferBase64 = GpaFilter::memcmp(8, $buffer, 'base64');
        self::assertSame('base64', $byBufferBase64['memcmp']['encoding']);
        self::assertSame(base64_encode("\xDE\xAD\xBE\xEF"), $byBufferBase64['memcmp']['bytes']);

        $byString = GpaFilter::memcmp(16, 'arbitrary-base58-string');
        self::assertSame('arbitrary-base58-string', $byString['memcmp']['bytes']);
    }

    #[Test]
    public function memcmp_rejects_negative_offset(): void
    {
        $this->expectException(InputValidationException::class);

        GpaFilter::memcmp(-1, Keypair::generate()->getPublicKey());
    }

    #[Test]
    public function data_size_builder_returns_canonical_shape(): void
    {
        $filter = GpaFilter::dataSize(165);

        self::assertSame(['dataSize' => 165], $filter);
    }

    #[Test]
    public function data_size_rejects_negative(): void
    {
        $this->expectException(InputValidationException::class);

        GpaFilter::dataSize(-1);
    }

    #[Test]
    public function connection_typed_accessor_returns_program_account_dtos(): void
    {
        $payload = base64_encode('hi');

        $this->fakeRpcByMethod([
            'getProgramAccounts' => [
                [
                    'pubkey' => '11111111111111111111111111111111',
                    'account' => [
                        'lamports' => 1,
                        'owner' => '11111111111111111111111111111111',
                        'executable' => false,
                        'rentEpoch' => 0,
                        'data' => [$payload, 'base64'],
                    ],
                ],
                [
                    'pubkey' => '11111111111111111111111111111111',
                    'account' => [
                        'lamports' => 2,
                        'owner' => '11111111111111111111111111111111',
                        'executable' => false,
                        'rentEpoch' => 0,
                        'data' => [$payload, 'base64'],
                    ],
                ],
            ],
        ]);

        $rows = $this->container->get(Connection::class)->programAccounts(PublicKey::default());

        self::assertCount(2, $rows);
        self::assertInstanceOf(ProgramAccount::class, $rows[0]);
        self::assertSame(1, $rows[0]->account->lamports);
        self::assertSame(2, $rows[1]->account->lamports);
    }
}
