<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Action;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Key;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;

#[Flow\Proxy(false)]
final class Signer
{
    private int $version = 5;
    private string $reserved = '0.0.0.0';
    private ?Path $path = null;
    private ?Key $key = null;
    private ?\DateTimeInterface $time = null;
    private ?Action $action = null;

    public function withPath(Path $path): self
    {
        $self = clone $this;
        $self->path = $path;
        return $self;
    }

    public function withKey(Key $key): self
    {
        $self = clone $this;
        $self->key = $key;
        return $self;
    }

    public function withTime(\DateTimeInterface $time): self
    {
        $self = clone $this;
        $self->time = $time;
        return $self;
    }

    public function withAction(Action $action): self
    {
        $self = clone $this;
        $self->action = $action;
        return $self;
    }

    public function authenticationHeaders()
    {
        $data = implode(", ", [
            $this->version,
            $this->reserved,
            $this->reserved,
            $this->time ? $this->time->format('U') : (new \DateTimeImmutable())->format('U'),
            Algorithms::generateRandomString(32),
            $this->key->getName()
        ]);

        return [
            'X-Akamai-ACS-Auth-Data' => $data,
            'X-Akamai-ACS-Auth-Sign' => $this->sign($data)
        ];
    }

    /**
     * Returns a signature for the request
     *
     * @param string $data
     * @return string
     */
    private function sign(string $data): string
    {
        $value = implode('', [
            $data,
            '/' . (string) $this->path,
            "\n",
            "x-akamai-acs-action:" . trim((string) $this->action),
            "\n"
        ]);

        return base64_encode(
            hash_hmac(
                'sha256', $value, $this->key->getValue(), true
            )
        );
    }

}
