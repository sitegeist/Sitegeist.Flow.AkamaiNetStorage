<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Filename
{
    protected function __construct(
        protected string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        #return new self(ltrim($value, '\\/'));
        return new self(rtrim(ltrim($value, '\\/'), '\\/'));
    }

    public function urlEncode(): self
    {
        return self::fromString(urlencode($this->value));
    }

    public function urlDecode(): self
    {
        return self::fromString(urldecode($this->value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
