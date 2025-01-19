<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\CompiledInstruction;
use Collectiq\SolanaPhpSdk\Util\MessageHeader;
use Collectiq\SolanaPhpSdk\Util\ShortVec;

final class Message
{
    /**
     * @var array<PublicKey>
     */
    public array $accountKeys;

    /**
     * int to PublicKey: https://github.com/solana-labs/solana-web3.js/blob/966d7c653198de193f607cdfe19a161420408df2/src/message.ts
     */
    private array $indexToProgramIds = [];

    /**
     * @param array<string> $accountKeys
     * @param array<CompiledInstruction> $instructions
     */
    public function __construct(
        public MessageHeader $header,
        array $accountKeys,
        public string $recentBlockhash,
        public array $instructions
    ) {
        $this->accountKeys = array_map(PublicKey::fromString(...), $accountKeys);

        foreach ($this->instructions as $instruction) {
            $this->indexToProgramIds[$instruction->programIdIndex] = $this->accountKeys[$instruction->programIdIndex];
        }
    }

    public function isAccountSigner(int $index): bool
    {
        return $index < $this->header->numRequiredSignature;
    }

    public function isAccountWritable(int $index): bool
    {
        return $index < ($this->header->numRequiredSignature - $this->header->numReadonlySignedAccounts)
            || ($index >= $this->header->numRequiredSignature && $index < count($this->accountKeys) - $this->header->numReadonlyUnsignedAccounts);
    }

    public function isProgramId(int $index): bool
    {
        return array_key_exists($index, $this->indexToProgramIds);
    }

    /**
     * @return array<PublicKey>
     */
    public function programIds(): array
    {
        return array_values($this->indexToProgramIds);
    }

    public function nonProgramIds(): array
    {
        return array_filter($this->accountKeys, function (PublicKey $account, $index): bool {
            return ! $this->isProgramId($index);
        });
    }

    public function serialize(): string
    {
        $out = Buffer::empty();

        $out->push($this->encodeMessage())
            ->push(ShortVec::encodeLength(count($this->instructions)));

        foreach ($this->instructions as $instruction) {
            $out->push($this->encodeInstruction($instruction));
        }

        return $out->toString();
    }

    private function encodeMessage(): array
    {
        $publicKeys = [];

        foreach ($this->accountKeys as $publicKey) {
            array_push($publicKeys, ...$publicKey->toBytes());
        }

        return [
            // uint8
            ...unpack('C*', pack('C', $this->header->numRequiredSignature)),
            // uint8
            ...unpack('C*', pack('C', $this->header->numReadonlySignedAccounts)),
            // uint8
            ...unpack('C*', pack('C', $this->header->numReadonlyUnsignedAccounts)),

            ...ShortVec::encodeLength(count($this->accountKeys)),
            ...$publicKeys,
            ...Buffer::fromBase58($this->recentBlockhash)->toArray(),
        ];
    }

    private function encodeInstruction(CompiledInstruction $instruction): array
    {
        $data = $instruction->data;

        $accounts = $instruction->accounts;

        return [
            // uint8
            ...unpack('C*', pack('C', $instruction->programIdIndex)),

            ...ShortVec::encodeLength(count($accounts)),
            ...$accounts,

            ...ShortVec::encodeLength($data->length()),
            ...$data->toArray(),
        ];
    }

    public static function from(array|Buffer $rawMessage): Message
    {
        $rawMessage = Buffer::from($rawMessage);

        $HEADER_OFFSET = 3;
        if ($rawMessage->length() < $HEADER_OFFSET) {
            throw new InputValidationException('Byte representation of message is missing message header.');
        }

        $numRequiredSignatures = $rawMessage->shift();
        $numReadonlySignedAccounts = $rawMessage->shift();
        $numReadonlyUnsignedAccounts = $rawMessage->shift();
        $header = new MessageHeader($numRequiredSignatures, $numReadonlySignedAccounts, $numReadonlyUnsignedAccounts);

        $accountKeys = [];
        [$accountsLength, $accountsOffset] = ShortVec::decodeLength($rawMessage);
        for ($i = 0; $i < $accountsLength; $i++) {
            $keyBytes = $rawMessage->slice($accountsOffset, PublicKey::$fixedLength);
            $accountKeys[] = PublicKey::fromBuffer($keyBytes)->toBase58();
            $accountsOffset += PublicKey::$fixedLength;
        }

        $rawMessage = $rawMessage->slice($accountsOffset);

        $recentBlockhash = $rawMessage->slice(0, PublicKey::$fixedLength)->toBase58String();
        $rawMessage = $rawMessage->slice(PublicKey::$fixedLength);

        $instructions = [];
        [$instructionCount, $offset] = ShortVec::decodeLength($rawMessage);
        $rawMessage = $rawMessage->slice($offset);
        for ($i = 0; $i < $instructionCount; $i++) {
            $programIdIndex = $rawMessage->shift();

            [$accountsLength, $offset] = ShortVec::decodeLength($rawMessage);
            $rawMessage = $rawMessage->slice($offset);
            $accounts = $rawMessage->slice(0, $accountsLength)->toArray();
            $rawMessage = $rawMessage->slice($accountsLength);

            [$dataLength, $offset] = ShortVec::decodeLength($rawMessage);
            $rawMessage = $rawMessage->slice($offset);
            $data = $rawMessage->slice(0, $dataLength);
            $rawMessage = $rawMessage->slice($dataLength);

            $instructions[] = new CompiledInstruction(
                programIdIndex: $programIdIndex,
                accounts: $accounts,
                data: $data,
            );
        }

        return new Message(
            header: $header,
            accountKeys: $accountKeys,
            recentBlockhash: $recentBlockhash,
            instructions: $instructions,
        );
    }
}
