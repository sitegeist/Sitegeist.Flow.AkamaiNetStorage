<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Action
{
    protected function __construct(
        protected string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * @param array<string, string>|null $options
     */
    public function acsActionHeader(array $options = null): string
    {
        $header = 'version=1&action=' . rawurlencode($this->value);
        $header .= ($options !== null ? '&' . http_build_query($options) : '');
        if (in_array($this->value, ['dir', 'download', 'du', 'stat'])) {
            $header .= '&format=xml';
        }

        return $header;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
