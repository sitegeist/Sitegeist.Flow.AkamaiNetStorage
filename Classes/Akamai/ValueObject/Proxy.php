<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Proxy
{
    protected function __construct(
        protected ?string $http = null,
        protected ?string $https = null
    ) {
    }

    public static function create(string $http = null, string $https = null): self
    {
        return new self($http, $https);
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'http' => $this->http,
            'https' => $this->https
        ];
    }
}
