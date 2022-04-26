<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

use Neos\Flow\Core\Booting\Scripts;
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
     * @Flow\InjectConfiguration(package="Neos.Flow")
     * @var array
     */
    protected $settings;

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

    public function listCommand(string $orderBy = 'name', string $path = '/')
    {
        if (in_array($orderBy, ['name', 'mtime']) === false) {
            $this->outputLine('--order-by must be either "name" or "mtime". "%s" was given',  [$orderBy]);
            $this->quit(1);
        }

        $options = $path ? Arrays::arrayMergeRecursiveOverrule($this->connectorOptions, ['workingDirectory' => $path]) : $this->connectorOptions;
        $connector = new Connector($options, 'cli');
        $contentList = $connector->getContentList(false);

        usort($contentList, function($a, $b) use ($orderBy) {
            if ($a[$orderBy] == $b[$orderBy]) {
                return 0;
            }
            return ($a[$orderBy] < $b[$orderBy]) ? 1 : -1;
        });

        $headers = ['name', 'type', 'mtime'];
        $rows = [];
        foreach ($contentList as $content) {
            $rows[] = [$content['name'], $content['type'], (\DateTime::createFromFormat('U', $content['timestamp']))->format(\DateTimeInterface::ISO8601)];
        }
        $this->output->outputTable($rows, $headers, $path);
    }

    public function deleteCommand(string $path, bool $yes = false)
    {
        if ($yes === false) {
            $yes = $this->output->askConfirmation(sprintf('This will delete "%s". Type "yes" to continue' . PHP_EOL, $path), false);

            if ($yes === false) {
                $this->outputLine('Deletion cancelled');
                $this->quit(1);
            }
        }

        $connector = new Connector($this->connectorOptions, 'cli');

        $connector->createFilesystem()->delete($path);
    }

    public function cleanupCommand(string $orderBy, string $path = '/', int $keep = 10, bool $yes = false) {
        if (in_array($orderBy, ['name', 'mtime']) === false) {
            $this->outputLine('--order-by must be either "name" or "mtime". "%s" was given',  [$orderBy]);
            $this->quit(1);
        }

        $options = $path ? Arrays::arrayMergeRecursiveOverrule($this->connectorOptions, ['workingDirectory' => $path]) : $this->connectorOptions;
        $connector = new Connector($options, 'cli');
        $contentList = $connector->getContentList(false);

        usort($contentList, function($a, $b) use ($orderBy) {
            if ($a[$orderBy] == $b[$orderBy]) {
                return 0;
            }
            return ($a[$orderBy] < $b[$orderBy]) ? 1 : -1;
        });

        $deletableFolders = array_slice($contentList, $keep);
        $this->outputLine('The following content will be deleted');
        $headers = ['name', 'type', 'mtime'];
        $rows = [];
        foreach ($deletableFolders as $content) {
            $rows[] = [$content['name'], $content['type'], (\DateTime::createFromFormat('U', $content['timestamp']))->format(\DateTimeInterface::ISO8601)];
        }
        $this->output->outputTable($rows, $headers, $connector->getFullDirectory());


        if ($yes === false) {
            $yes = $this->output->askConfirmation(sprintf('To cleanup the path "%s", you must type "yes"' . PHP_EOL, $path), false);

            if ($yes === false) {
                $this->outputLine('Cleanup cancelled');
                $this->quit(1);
            }
        }

        foreach ($deletableFolders as $path) {
            $this->outputLine('<info>Deleting "%s"</info>', [$path['path']]);
            Scripts::executeCommand('akamai:delete', $this->settings, false, ['path' => $path['path'], 'yes' => $yes]);
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
