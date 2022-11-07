<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\UriInterface;

#[Flow\Proxy(false)]
final class Host
{
    protected function __construct(
        protected string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toUri(): UriInterface
    {
        return new Uri('https://' . $this->value);
    }

    public function __toString(): string
    {
        return rtrim($this->value, '\\/');
    }
}
