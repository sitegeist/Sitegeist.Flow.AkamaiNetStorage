<?php

declare(strict_types=1);

namespace Sitegeist\Flow\AkamaiNetStorage;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\Client;

trait AkamaiClientTrait
{
    /**
     * @var Client[]
     */
    protected $clientCache = [];

    /**
     * @Flow\InjectConfiguration(path="options")
     * @var array<string, mixed>
     */
    protected $connectorOptions;

    /**
     * @param string $name
     * @param array<string, mixed> $options
     * @return Client
     */
    public function getClient(string $name, array $options = []): Client
    {
        $hash = md5($name . json_encode($options));
        if (array_key_exists($hash, $this->clientCache)) {
            return $this->clientCache[$hash];
        }

        /* @phpstan-ignore-next-line */
        $options =  Arrays::arrayMergeRecursiveOverrule($this->connectorOptions, $options);
        $this->clientCache[$hash] = Client::fromOptions($options);
        return $this->clientCache[$hash];
    }
}