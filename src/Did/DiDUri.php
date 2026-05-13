<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Did;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Illuminate\Support\Uri;
use JsonSerializable;
use SanderMuller\SolanaPhpSdk\Enum\Network;

final readonly class DiDUri implements \SanderMuller\SolanaPhpSdk\Util\Stringable, JsonSerializable
{
    /**
     * DID scheme.
     */
    private const string SCHEME = 'did';

    private const string PATH_DELIMITER = '/';

    private const string FRAGMENT_DELIMITER = '#';

    /**
     * Regular expression for decoding DID.
     */
    private const string REGEX = '~^' . self::SCHEME . ':([a-z]+):([A-Za-z0-9:.-]+)(?<!:)(/[A-Za-z0-9-._\~%!$&\'()*+,;=:@/]*)?(#[A-Za-z0-9-._\~%!$&\'()*+,;=:@/?]*)?$~';

    public ?Stringable $path;

    public ?Stringable $fragment;

    public function __construct(
        public Stringable $method,
        /**
         * @var Collection<int, string>
         */
        public Collection $ids,
        ?Stringable       $path = null,
        ?Stringable       $fragment = null
    ) {
        if ($this->method->isEmpty()) {
            throw new DidEncodingException('Method cannot be empty');
        }

        if ($this->ids->isEmpty()) {
            throw new DidEncodingException('ID cannot be empty');
        }

        foreach ($this->ids as $id) {
            if (! is_string($id)) {
                throw new DidEncodingException('DID ids must all be strings.');
            }
        }

        $this->path = $path instanceof Stringable
            ? $this->normalizePath($path)
            : null;
        $this->fragment = $fragment instanceof Stringable
            ? $this->normalizeFragment($fragment)
            : null;
    }

    public static function parse(Uri $uri): self
    {
        $input = $uri->__toString();

        if (preg_match(self::REGEX, $input, $matches) !== 1) {
            throw new DidEncodingException("URI \"{$uri}\" is not a valid DID");
        }

        [, $method, $ids, $path, $fragment] = $matches + [null, null, null, '', ''];

        return new self(
            method: str($method),
            ids: new Collection(explode(':', $ids)),
            path: $path !== '' ? str($path) : null,
            fragment: $fragment !== '' ? str($fragment) : null,
        );
    }

    public static function isDid(Uri $uri): bool
    {
        return str($uri->__toString())->matchAll(self::REGEX)->isNotEmpty();
    }

    private function normalizePath(Stringable $path): ?Stringable
    {
        if ($path->isEmpty() || $path->exactly(self::PATH_DELIMITER)) {
            return null;
        }

        return $path
            ->trim(self::PATH_DELIMITER)
            ->prepend(self::PATH_DELIMITER);
    }

    private function normalizeFragment(Stringable $fragment): ?Stringable
    {
        if ($fragment->isEmpty() || $fragment->exactly(self::FRAGMENT_DELIMITER)) {
            return null;
        }

        return $fragment->ltrim(self::FRAGMENT_DELIMITER);
    }

    public function isSolana(): bool
    {
        return $this->method->exactly('sol');
    }

    public function toNetwork(): Network
    {
        if (! $this->isSolana()) {
            throw new Exception('Unsupported DID method, use did:sol:[network:]base58SubjectPK');
        }

        if ($this->ids->count() === 3) {
            return Network::MAINNET;
        }

        if ($this->ids->count() === 4) {
            $cluster = $this->ids->offsetGet(2);

            return is_string($cluster) ? (Network::tryFrom($cluster) ?? Network::MAINNET) : Network::MAINNET;
        }

        return Network::MAINNET;
    }

    public function base58SubjectPK(): ?string
    {
        if (! $this->isSolana()) {
            throw new Exception('Unsupported DID method, use did:sol:[network:]base58SubjectPK');
        }

        if ($this->ids->count() === 4) {
            return $this->ids->offsetGet(3);
        }

        if ($this->ids->count() === 3) {
            return $this->ids->offsetGet(2);
        }

        return null;
    }

    public function jsonSerialize(): string
    {
        return sprintf('%s:%s:%s%s%s',
            self::SCHEME,
            $this->method,
            $this->ids->implode(':'),
            $this->path,
            $this->fragment instanceof Stringable
                ? $this->fragment->ltrim(self::FRAGMENT_DELIMITER)->prepend(self::FRAGMENT_DELIMITER)
                : '',
        );
    }

    public function toString(): string
    {
        return $this->jsonSerialize();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
