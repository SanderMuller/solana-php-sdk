<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

use Collectiq\SolanaPhpSdk\Exceptions\InputValidationException;
use Collectiq\SolanaPhpSdk\Support\PublicKeyCollection;
use Collectiq\SolanaPhpSdk\Util\Buffer;
use Collectiq\SolanaPhpSdk\Util\CompiledInstruction;
use Collectiq\SolanaPhpSdk\Util\MessageHeader;
use Collectiq\SolanaPhpSdk\Util\ShortVec;

final readonly class Message implements VersionedMessage
{
    private const int HEADER_OFFSET = 3;

    /**
     * Map of instruction `programIdIndex` -> resolved {@see PublicKey} drawn
     * from {@see $accountKeys}.
     *
     * @var array<int, PublicKey>
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
            $programId = $this->accountKeys->get($instruction->programIdIndex);
            if ($programId instanceof PublicKey) {
                $indexToProgramIds[$instruction->programIdIndex] = $programId;
            }
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
     * @return array<int, PublicKey>
     */
    public function programIds(): array
    {
        return array_values($this->indexToProgramIds);
    }

    public function nonProgramIds(): PublicKeyCollection
    {
        return $this->accountKeys->reject(
            fn (PublicKey $account, int $index): bool => $this->isProgramId($index)
        );
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

    /**
     * @return array<int, int>
     */
    private function encodeMessage(): array
    {
        $bytes = [
            $this->header->numRequiredSignature & 0xFF,
            $this->header->numReadonlySignedAccounts & 0xFF,
            $this->header->numReadonlyUnsignedAccounts & 0xFF,
            ...ShortVec::encodeLength($this->accountKeys->count()),
        ];

        foreach ($this->accountKeys as $publicKey) {
            foreach ($publicKey->toBytes() as $byte) {
                $bytes[] = $byte;
            }
        }

        foreach (Buffer::fromBase58((string) $this->recentBlockhash)->toArray() as $byte) {
            $bytes[] = $byte;
        }

        return $bytes;
    }

    /**
     * @return array<int, int>
     */
    private function encodeInstruction(CompiledInstruction $instruction): array
    {
        $data = $instruction->data;
        $accounts = $instruction->accounts;

        return [
            $instruction->programIdIndex & 0xFF,
            ...ShortVec::encodeLength(count($accounts)),
            ...$accounts,
            ...ShortVec::encodeLength($data->length()),
            ...$data->toArray(),
        ];
    }

    public function version(): ?int
    {
        return null;
    }

    public function header(): MessageHeader
    {
        return $this->header;
    }

    public function staticAccountKeys(): PublicKeyCollection
    {
        return $this->accountKeys;
    }

    public function recentBlockhash(): string
    {
        return $this->recentBlockhash instanceof PublicKey
            ? $this->recentBlockhash->toBase58()
            : $this->recentBlockhash;
    }

    /**
     * @return array<CompiledInstruction>
     */
    public function compiledInstructions(): array
    {
        return $this->instructions;
    }

    /**
     * @param Buffer|array<int, int>|string $buffer
     */
    public static function deserialize(Buffer|array|string $buffer): self
    {
        if (is_string($buffer)) {
            $buffer = Buffer::fromString($buffer);
        }

        return self::from($buffer);
    }

    /**
     * @param Buffer|array<int, int> $rawMessage
     */
    public static function from(array|Buffer $rawMessage): Message
    {
        $rawMessage = Buffer::from($rawMessage);

        if ($rawMessage->length() < self::HEADER_OFFSET) {
            throw new InputValidationException('Byte representation of message is missing message header.');
        }

        $numRequiredSignatures = (int) $rawMessage->shift();
        $numReadonlySignedAccounts = (int) $rawMessage->shift();
        $numReadonlyUnsignedAccounts = (int) $rawMessage->shift();

        $header = new MessageHeader(
            numRequiredSignature: $numRequiredSignatures,
            numReadonlySignedAccounts: $numReadonlySignedAccounts,
            numReadonlyUnsignedAccounts: $numReadonlyUnsignedAccounts,
        );

        $pubkeyLength = PublicKey::$fixedLength ?? 32;

        $accountKeys = PublicKeyCollection::empty();
        [$accountsLength, $accountsOffset] = ShortVec::decodeLength($rawMessage);
        for ($i = 0; $i < $accountsLength; $i++) {
            $keyBytes = $rawMessage->slice($accountsOffset, $pubkeyLength);
            $accountKeys->add(PublicKey::from($keyBytes));
            $accountsOffset += $pubkeyLength;
        }

        $rawMessage = $rawMessage->slice($accountsOffset);

        $recentBlockhash = PublicKey::from(
            $rawMessage->slice(0, $pubkeyLength)->toBase58String()
        );

        $rawMessage = $rawMessage->slice($pubkeyLength);

        $instructions = [];
        [$instructionCount, $offset] = ShortVec::decodeLength($rawMessage);
        $rawMessage = $rawMessage->slice((int) $offset);

        for ($i = 0; $i < $instructionCount; $i++) {
            $programIdIndex = (int) $rawMessage->shift();

            [$accountsLength, $offset] = ShortVec::decodeLength($rawMessage);
            $rawMessage = $rawMessage->slice((int) $offset);
            /** @var array<int, int> $accounts */
            $accounts = $rawMessage->slice(0, (int) $accountsLength)->toArray();
            $rawMessage = $rawMessage->slice((int) $accountsLength);

            [$dataLength, $offset] = ShortVec::decodeLength($rawMessage);
            $rawMessage = $rawMessage->slice((int) $offset);
            $data = $rawMessage->slice(0, (int) $dataLength);
            $rawMessage = $rawMessage->slice((int) $dataLength);

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
