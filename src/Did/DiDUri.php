<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Did;

use Collectiq\SolanaPhpSdk\Enum\Network;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Illuminate\Support\Uri;
use JsonSerializable;

final readonly class DiDUri implements \Stringable, JsonSerializable
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

        $this->ids->ensure('string');

        $this->path = $path instanceof \Illuminate\Support\Stringable
            ? $this->normalizePath($path)
            : null;
        $this->fragment = $fragment instanceof \Illuminate\Support\Stringable
            ? $this->normalizeFragment($fragment)
            : null;
    }

    public static function parse(Uri $uri): self
    {
        $matches = str($uri->__toString())->matchAll(self::REGEX);

        dd($matches, $uri);

        if ($matches->isEmpty()) {
            throw new DidEncodingException("URI \"{$uri}\" is not a a valid DID");
        }

        $scheme = $matches->shift();
        if ($scheme !== self::SCHEME) {
            throw new DidEncodingException("Scheme \"{$scheme}\" is not a DID scheme");
        }

        $method = $matches->shift();
        assert(is_string($method));

        $matches->shift();

        [
            $scheme,
            $method,
            $ids,
            $path,
            $fragment,
        ] = $matches->all();

        // $matches[2] is one or more colon-delimited ids, break into array for ctor
        return new self(str($method), explode(':', (string) $matches[2]), $matches[3], $matches[4]);
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
            return Network::tryFrom($this->ids->offsetGet(2)) ?? Network::MAINNET;
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
            $this->fragment instanceof \Illuminate\Support\Stringable
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
