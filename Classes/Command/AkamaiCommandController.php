<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;

use Sitegeist\Flow\AkamaiNetStorage\AkamaiStorage;
use Sitegeist\Flow\AkamaiNetStorage\AkamaiTarget;
use Sitegeist\Flow\AkamaiNetStorage\Connector;
use Symfony\Component\Yaml\Yaml;

/**
 * Akamai NetStorage command controller
 *
 * @Flow\Scope("singleton")
 */
class AkamaiCommandController extends CommandController {
    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var ResourceRepository
     */
    protected $resourceRepository;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="defaultConnector.options")
     */
    protected $connectorOptions;

    function __construct() {
        parent::__construct();
        $this->resourceManager = new ResourceManager;
        $this->resourceRepository = new ResourceRepository;
    }

    /**
     *
     */
    public function showConfigCommand() {
        $yaml = Yaml::dump($this->connectorOptions, 99);
        $this->outputLine($yaml . "\n");
    }

    /**
     *
     */
    public function connectCommand() {
        $connector = $this->getDefaultAkamaiStorageConnector();
        if ($connector) {
            $this->outputLine('connection is working: ' . ($connector->testConnection() ? 'yes ;)' : 'no'));
        } else {
            $this->outputLine("No akamai default connector found.\n");
        }
    }

    /**
     * @param string $directory
     */
    public function listCommand(string $directory) {
        $connector = $this->getDefaultAkamaiStorageConnector();
        if ($connector) {
            $meta = $connector->createFilesystem()->getMetadata($connector->getRestrictedDirectory() . '/' . $directory);

            $pathes = $connector->collectDirectoryPathes($directory);
            \Neos\Flow\var_dump($pathes);
        } else {
            $this->outputLine("No akamai default connector found.\n");
        }
    }

    /**
     * @param string $collectionName
     */
    public function connectCollectionCommand($collectionName) {
        $storageConnector = $this->getAkamaiStorageConnectorByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetConnectorByCollectionName($collectionName);

        if ($storageConnector) {
            $this->outputLine('storage connection is working: ' . ($storageConnector->testConnection() ? 'yes ;)' : 'no'));
        } else {
            $this->outputLine("No akamai connector found for storage in collection " . $collectionName . "\n");
        }

        if ($targetConnector) {
            $this->outputLine('target connection is working: ' . ($targetConnector->testConnection() ? 'yes ;)' : 'no'));
        } else {
            $this->outputLine("No akamai connector found for target in collection " . $collectionName . "\n");
        }
    }

    /**
     * @param string $collectionName
     */
    public function listCollectionCommand($collectionName) {
        $storageConnector = $this->getAkamaiStorageConnectorByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetConnectorByCollectionName($collectionName);

        if ($storageConnector) {
            $this->outputLine('');
            $this->outputLine('storage connector listing:');
            $this->outputLine('------------------------------------------------------');

            foreach ($storageConnector->collectAllPaths() as $path) {
                $this->outputLine($path);
            }
        } else {
            echo "No akamai connector found for storage in collection " . $collectionName . "\n";
        }

        if ($targetConnector) {
            $this->outputLine('');
            $this->outputLine("target connector listing:");
            $this->outputLine('------------------------------------------------------');

            foreach ($targetConnector->collectAllPaths() as $path) {
                $this->outputLine($path);
            }
        } else {
            echo "No akamai connector found for target in collection " . $collectionName . "\n";
        }
    }

    /**
     * @return Connector|null
     */
    private function getDefaultAkamaiStorageConnector(): ?Connector
    {
        $options = $this->connectorOptions;
        $connector = new Connector($options, 'default');
        return $connector;
    }

    /**
     * @param string $collectionName
     * @return Connector | null
     */
    private function getAkamaiStorageConnectorByCollectionName($collectionName): ?Connector
    {
        $collection = $this->resourceManager->getCollection($collectionName);
        $storage = $collection->getStorage();

        if ($storage instanceof AkamaiStorage) {
            return $storage->getConnector();
        } else {
            return null;
        }
    }

    /**
     * @param string $collectionName
     * @return Connector | null
     */
    private function getAkamaiTargetConnectorByCollectionName($collectionName): ?Connector
    {
        $collection = $this->resourceManager->getCollection($collectionName);
        $target = $collection->getTarget();

        if ($target instanceof AkamaiTarget) {
            return $target->getConnector();
        } else {
            return null;
        }
    }

    /**
     * Danger!!! removes all folders and files for collection
     *
     * @param string $collectionName
     * @param string $areYouSure
     */
    public function nukeCommand($collectionName, $areYouSure) {
        $storageConnector = $this->getAkamaiStorageConnectorByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetConnectorByCollectionName($collectionName);

        if ($storageConnector && $areYouSure) {
            $this->outputLine('');
            $this->outputLine('removing files for storage connector');
            $this->outputLine('------------------------------------------------------');
            $storageConnector->removeAllFiles();
        } else {
            $this->outputLine('');
            $this->outputLine('No akamai connector found for storage in collection ' . $collectionName);
            $this->outputLine('------------------------------------------------------');
        }

        if ($targetConnector && $areYouSure) {
            $this->outputLine('');
            $this->outputLine('removing files for target connector');
            $this->outputLine('------------------------------------------------------');
            $targetConnector->removeAllFiles();
        } else {
            $this->outputLine('');
            $this->outputLine('No akamai connector found for target in collection ' . $collectionName);
            $this->outputLine('------------------------------------------------------');
        }
    }
}
