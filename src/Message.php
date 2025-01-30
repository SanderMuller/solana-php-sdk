<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Support\PublicKeyCollection;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\CompiledInstruction;
use Collectiq\SolanaPhpSdk\Util\MessageHeader;
use Collectiq\SolanaPhpSdk\Util\ShortVec;

final readonly class Message
{
    private const int HEADER_OFFSET = 3;

    /**
     * int to PublicKey: https://github.com/solana-labs/solana-web3.js/blob/966d7c653198de193f607cdfe19a161420408df2/src/message.ts
     */
    private array $indexToProgramIds;

    /**
     * @param array<CompiledInstruction> $instructions
     */
    public function __construct(
        public MessageHeader       $header,
        public PublicKeyCollection $accountKeys,
        public string|PublicKey    $recentBlockhash,
        public array               $instructions,
    ) {
        $indexToProgramIds = [];

        foreach ($this->instructions as $instruction) {
            $indexToProgramIds[$instruction->programIdIndex] = $this->accountKeys->get($instruction->programIdIndex);
        }

        $this->indexToProgramIds = $indexToProgramIds;
    }

    public function isAccountSigner(int $index): bool
    {
        return $index < $this->header->numRequiredSignature;
    }

    public function isAccountWritable(int $index): bool
    {
        if ($index < ($this->header->numRequiredSignature - $this->header->numReadonlySignedAccounts)) {
            return true;
        }

        return $index >= $this->header->numRequiredSignature
            && $index < $this->accountKeys->count() - $this->header->numReadonlyUnsignedAccounts;
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
        return $this->accountKeys->reject(fn (PublicKey $account, int $index): bool => $this->isProgramId($index));
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
        return [
            // uint8
            ...unpack('C*', pack('C', $this->header->numRequiredSignature)),
            // uint8
            ...unpack('C*', pack('C', $this->header->numReadonlySignedAccounts)),
            // uint8
            ...unpack('C*', pack('C', $this->header->numReadonlyUnsignedAccounts)),

            ...ShortVec::encodeLength($this->accountKeys->count()),

            ...$this->accountKeys
                ->map(fn (PublicKey $publicKey): array => $publicKey->toBytes())
                ->all(),

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

        if ($rawMessage->length() < self::HEADER_OFFSET) {
            throw new InputValidationException('Byte representation of message is missing message header.');
        }

        $numRequiredSignatures = $rawMessage->shift();
        $numReadonlySignedAccounts = $rawMessage->shift();
        $numReadonlyUnsignedAccounts = $rawMessage->shift();

        $header = new MessageHeader(
            numRequiredSignature: $numRequiredSignatures,
            numReadonlySignedAccounts: $numReadonlySignedAccounts,
            numReadonlyUnsignedAccounts: $numReadonlyUnsignedAccounts,
        );

        $accountKeys = PublicKeyCollection::empty();
        [$accountsLength, $accountsOffset] = ShortVec::decodeLength($rawMessage);
        for ($i = 0; $i < $accountsLength; $i++) {
            $keyBytes = $rawMessage->slice($accountsOffset, PublicKey::$fixedLength);
            $accountKeys->add(PublicKey::from($keyBytes));
            $accountsOffset += PublicKey::$fixedLength;
        }

        $rawMessage = $rawMessage->slice($accountsOffset);

        $recentBlockhash = PublicKey::from(
            $rawMessage->slice(0, PublicKey::$fixedLength)->toBase58String()
        );

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
