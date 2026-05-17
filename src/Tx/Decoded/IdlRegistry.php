<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

use SanderMuller\SolanaPhpSdk\Anchor\AnchorIdl;
use SanderMuller\SolanaPhpSdk\Anchor\AnchorProgramDecoder;

/**
 * In-memory map of `programId` → {@see ProgramDecoder}. Bound `scoped`
 * in the Laravel container so each request gets a fresh registry;
 * Bootstrap binds it as a singleton outside Laravel. Hosts populate
 * during boot:
 *
 * ```php
 * $registry = new IdlRegistry();
 * $registry->registerAnchor(AnchorIdl::fromFile(base_path('idl/jupiter.json')));
 * ```
 *
 * @api
 */
final class IdlRegistry
{
    /** @var array<string, ProgramDecoder> keyed by base58 program id */
    private array $decoders = [];

    public function register(ProgramDecoder $decoder): void
    {
        $this->decoders[$decoder->programId()] = $decoder;
    }

    /**
     * Anchor-sugar registration: wraps the IDL in an
     * {@see AnchorProgramDecoder} and registers it.
     */
    public function registerAnchor(AnchorIdl $idl): void
    {
        $this->register(new AnchorProgramDecoder($idl));
    }

    public function forProgram(string $programId): ?ProgramDecoder
    {
        return $this->decoders[$programId] ?? null;
    }

    /**
     * @return list<string>
     */
    public function known(): array
    {
        return array_values(array_keys($this->decoders));
    }
}
