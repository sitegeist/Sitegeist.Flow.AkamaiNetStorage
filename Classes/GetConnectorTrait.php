<?php
declare(strict_types=1);

namespace Sitegeist\Flow\AkamaiNetStorage;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

trait GetConnectorTrait
{
    /**
     * @var \Sitegeist\Flow\AkamaiNetStorage\Connector[]
     */
    protected $connectorCache = [];

    /**
     * @Flow\InjectConfiguration(path="options")
     * @var array
     */
    protected $connectorOptions;

    /**
     * Returns the instance name of this storage
     *
     * @return \Sitegeist\Flow\AkamaiNetStorage\Connector
     */
    public function getConnector(string $name, array $options)
    {
        $hash = md5($name . json_encode($options));
        if (array_key_exists($hash, $this->connectorCache)) {
            return $this->connectorCache[$hash];
        }

        $options =  Arrays::arrayMergeRecursiveOverrule($this->connectorOptions ?? [], $options);
        $this->connectorCache[$hash] = new Connector($options, $name);
        return $this->connectorCache[$hash];
    }
}
