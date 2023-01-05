<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Path
{
    const DIRECTORY_SEPARATOR = '/';

    protected function __construct(
        protected string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(rtrim(ltrim($value, '\\/'), '\\/'));
    }

    public function append(Path $path): self
    {
        return self::fromString((string) $this . self::DIRECTORY_SEPARATOR . (string) $path);
    }



    public function containsPathPart(Path $path): bool
    {
        return array_filter(
            explode(self::DIRECTORY_SEPARATOR, $this->value),
            fn(string $part) => ($part === (string) $path)
        ) !== [];
    }

    public function ltrim(Path $path): self
    {
        return new self(ltrim($this->value, (string) $path));
    }

    public function prepend(Path $path): self
    {
        return self::fromString((string) $path . self::DIRECTORY_SEPARATOR . (string) $this);
    }

    public function equals(Path $path): bool
    {
        return ((string) $this === (string) $path);
    }

    public function urlEncode(): self
    {
        return self::fromString(urlencode($this->value));
    }

    public function urlDecode(): self
    {
        return self::fromString(urldecode($this->value));
    }

    public static function root(): self
    {
        return new self('');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
