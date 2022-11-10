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

    public function prepend(Path $path): self
    {
        return self::fromString((string) $path . self::DIRECTORY_SEPARATOR . (string) $this);
    }

    public function equals(Path $path): bool
    {
        return ((string) $this === (string) $path);
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
