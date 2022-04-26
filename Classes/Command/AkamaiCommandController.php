<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;

use Neos\Utility\Arrays;
use Sitegeist\Flow\AkamaiNetStorage\AkamaiStorage;
use Sitegeist\Flow\AkamaiNetStorage\AkamaiTarget;
use Sitegeist\Flow\AkamaiNetStorage\Connector;

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
     * @Flow\InjectConfiguration(path="options")
     * @var array
     */
    protected $connectorOptions;

    function __construct() {
        parent::__construct();
        $this->resourceManager = new ResourceManager;
        $this->resourceRepository = new ResourceRepository;
    }

    /**
     * @param string $collectionName
     */
    public function connectCommand($collectionName) {
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

    public function listCommand(string $workingDirectory = '/')
    {
        $options = $workingDirectory ? Arrays::arrayMergeRecursiveOverrule($this->connectorOptions, ['workingDirectory' => $workingDirectory]) : $this->connectorOptions;
        $connector = new Connector($options, 'cli');
        $headers = ['name', 'type', 'mtime'];
        $rows = [];
        foreach ($connector->getContentList(false) as $path) {
            $rows[] = [$path['name'], $path['type'], (\DateTime::createFromFormat('U', $path['timestamp']))->format(\DateTimeInterface::ISO8601)];
        }
        $this->output->outputTable($rows, $headers, $workingDirectory);
    }

    public function cleanupCommand(string $workingDirectory = '/', int $keep = 10) {
        $options = $workingDirectory ? Arrays::arrayMergeRecursiveOverrule($this->connectorOptions, ['workingDirectory' => $workingDirectory]) : $this->connectorOptions;
        $connector = new Connector($options, 'cli');
        $paths = $connector->getContentList(false);

        usort($paths, function($a, $b) {
            if ($a['timestamp'] == $b['timestamp']) {
                return 0;
            }
            return ($a['timestamp'] < $b['timestamp']) ? 1 : -1;
        });

        $deletableFolders = array_slice($paths, $keep);

        #$filesystem = $connector->createFilesystem();
        foreach ($deletableFolders as $path) {
            $this->outputLine('Send delete command for folder "%s"', [$path['path']]);
            #$filesystem->delete($path['path']);
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
     * @param string $collectionName
     * @return Connector | null
     */
    private function getAkamaiStorageConnectorByCollectionName($collectionName) {
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
    private function getAkamaiTargetConnectorByCollectionName($collectionName) {
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
    public function nukeCollectionCommand($collectionName, $areYouSure) {
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
