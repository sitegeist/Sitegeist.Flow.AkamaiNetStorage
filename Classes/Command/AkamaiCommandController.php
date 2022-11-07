<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Command;

use League\Flysystem\FileNotFoundException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;
use Neos\Utility\Arrays;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\Client;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\Response\File;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;
use Sitegeist\Flow\AkamaiNetStorage\AkamaiClientTrait;
use Sitegeist\Flow\AkamaiNetStorage\AkamaiStorage;
use Sitegeist\Flow\AkamaiNetStorage\AkamaiTarget;
use Sitegeist\Flow\AkamaiNetStorage\Connector;
use Sitegeist\Flow\AkamaiNetStorage\Exception\FileDoesNotExistsException;

/**
 * Akamai NetStorage command controller
 *
 * @Flow\Scope("singleton")
 */
class AkamaiCommandController extends CommandController
{
    use AkamaiClientTrait;

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

    public function __construct()
    {
        parent::__construct();
        $this->resourceManager = new ResourceManager();
        $this->resourceRepository = new ResourceRepository();
    }

    /**
     * @param string $collectionName
     */
    public function connectCommand($collectionName)
    {
        $storageClient = $this->getAkamaiStorageClientByCollectionName($collectionName);
        $targetClient = $this->getAkamaiTargetClientByCollectionName($collectionName);

        if ($storageClient) {
            $this->outputLine('storage connection is working: ' . ($storageClient->canConnect() ? 'yes ;)' : 'no'));
        } else {
            $this->outputLine("No akamai connector found for storage in collection " . $collectionName . "\n");
        }

        if ($targetClient) {
            $this->outputLine('target connection is working: ' . ($targetClient->canConnect() ? 'yes ;)' : 'no'));
        } else {
            $this->outputLine("No akamai connector found for target in collection " . $collectionName . "\n");
        }
    }

    /**
     * @param string $orderBy 'name' (default) or 'mtime'
     * @param string $path default '/'
     * @return void
     * @throws \Neos\Flow\Cli\Exception\StopCommandException
     */
    public function listCommand(string $orderBy = 'name', string $path = '/')
    {
        if (in_array($orderBy, ['name', 'mtime']) === false) {
            $this->outputLine('--order-by must be either "name" or "mtime". "%s" was given', [$orderBy]);
            $this->quit(1);
        }

        $client = Client::fromOptions($this->connectorOptions);
        $directoryListing = $client->dir(Path::fromString($path));

        usort($directoryListing->files, function ($a, $b) use ($orderBy) {
            if ($a->$orderBy == $b->$orderBy) {
                return 0;
            }
            return ($a->$orderBy < $b->$orderBy) ? 1 : -1;
        });

        $headers = ['name', 'path', 'type', 'mtime'];
        $rows = [];
        /** @var File $file */
        foreach ($directoryListing->files as $file) {
            $rows[] = [
                $file->name,
                $file->path,
                $file->type,
                (\DateTime::createFromFormat('U', $file->mtime))->format(\DateTimeInterface::ISO8601)
            ];
        }
        $this->output->outputTable($rows, $headers, $path);
    }

    /**
     * @param string $path
     * @param bool $yes
     * @return void
     * @throws FileNotFoundException
     * @throws \Neos\Flow\Cli\Exception\StopCommandException
     */
    public function deleteCommand(string $path, bool $yes = false)
    {
        $client = Client::fromOptions($this->connectorOptions);
        $path = Path::fromString($path);

        if ($yes === false) {
            $yes = $this->output->askConfirmation(sprintf('This will delete "%s". Type "yes" to continue' . PHP_EOL, $path), false);

            if ($yes === false) {
                $this->outputLine('Deletion cancelled');
                $this->quit(1);
            }
        }

        try {
            $metadata = $client->stat($path);
        } catch (FileDoesNotExistsException $exception) {
            $this->outputLine('The Akamai path "%s" does not exists', [$path]);
            $this->quit(1);
        }

        if ($metadata->isDirectory()) {
            $client->rmdir($path);
        } else {
            $client->delete($path);
        }
    }

    /**
     * @param string $path
     * @return void
     * @throws \Neos\Flow\Cli\Exception\StopCommandException
     */
    public function metadataCommand(string $path)
    {
        $client = Client::fromOptions($this->connectorOptions);
        try {
            $metadata = $client->stat(Path::fromString($path));
        } catch (FileDoesNotExistsException $exception) {
            $this->outputLine('Path "%s" was not found', [$path]);
            $this->quit(1);
        }


        $headers = ['key', 'value'];
        $rows = [];
        foreach ($metadata as $key => $value) {
            $rows[] = [$key, $value];
        }
        $this->output->outputTable($rows, $headers, $path);
    }

    /**
     * @param string $orderBy 'name' or 'mtime'
     * @param string $path
     * @param int $keep
     * @param bool $yes
     * @return void
     * @throws \Neos\Flow\Cli\Exception\StopCommandException
     * @throws \Neos\Flow\Core\Booting\Exception\SubProcessException
     */
    public function cleanupCommand(string $orderBy, string $path = '/', int $keep = 10, bool $yes = false)
    {
        if (in_array($orderBy, ['name', 'mtime']) === false) {
            $this->outputLine('--order-by must be either "name" or "mtime". "%s" was given', [$orderBy]);
            $this->quit(1);
        }

        $options = $path ? Arrays::arrayMergeRecursiveOverrule($this->connectorOptions, ['workingDirectory' => $path]) : $this->connectorOptions;
        $client = Client::fromOptions($options);
        $directoryListing = $client->dir(Path::fromString($path));

        $files = $directoryListing->files;
        usort($files, function ($a, $b) use ($orderBy) {
            if ($a->$orderBy == $b->$orderBy) {
                return 0;
            }
            return ($a->$orderBy < $b->$orderBy) ? 1 : -1;
        });

        $deletablePaths = array_slice($files, $keep);

        if ($yes === false) {
            $this->outputLine('The following content will be deleted');
            $headers = ['name', 'path', 'type', 'mtime'];
            $rows = [];
            /** @var File $deletablePath */
            foreach ($deletablePaths as $deletablePath) {
                $rows[] = [
                    $deletablePath->name,
                    $deletablePath->fullPath(),
                    $deletablePath->type,
                    (\DateTime::createFromFormat('U', $deletablePath->mtime))->format(\DateTimeInterface::ISO8601)
                ];
            }
            $this->output->outputTable($rows, $headers, $path);

            $yes = $this->output->askConfirmation(sprintf('To cleanup the path "%s", you must type "yes"' . PHP_EOL, $path), false);

            if ($yes === false) {
                $this->outputLine('Cleanup cancelled');
                $this->quit(1);
            }
        }

        foreach ($deletablePaths as $deletablePath) {
            $this->outputLine('<info>Deleting "%s"</info>', [(string) $deletablePath->fullPath()]);
            #$this->deleteCommand($path . '/' . $deletablePath['name'], $yes);
        }
    }

    /**
     * @param string $collectionName
     */
    public function listCollectionCommand($collectionName)
    {
        $storageConnector = $this->getAkamaiStorageClientByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetClientByCollectionName($collectionName);

        if ($storageConnector) {
            $this->outputLine('');
            $this->outputLine('storage connector listing:');
            $this->outputLine('------------------------------------------------------');

            /** @var File $file */
            foreach ($storageConnector->dir(Path::root(), true)->files as $file) {
                $this->outputLine($file->fullPath());
            }
        } else {
            echo "No akamai connector found for storage in collection " . $collectionName . "\n";
        }

        if ($targetConnector) {
            $this->outputLine('');
            $this->outputLine("target connector listing:");
            $this->outputLine('------------------------------------------------------');

            foreach ($targetConnector->dir(Path::root(), true)->files as $file) {
                $this->outputLine($file->fullPath());
            }
        } else {
            echo "No akamai connector found for target in collection " . $collectionName . "\n";
        }
    }

    private function getAkamaiStorageClientByCollectionName(string $collectionName): ?Client
    {
        $collection = $this->resourceManager->getCollection($collectionName);
        $storage = $collection->getStorage();

        if ($storage instanceof AkamaiStorage) {
            return $storage->getClient($collectionName);
        } else {
            return null;
        }
    }

    private function getAkamaiTargetClientByCollectionName(string $collectionName): ?Client
    {
        $collection = $this->resourceManager->getCollection($collectionName);
        $target = $collection->getTarget();

        if ($target instanceof AkamaiTarget) {
            return $target->getClient($collectionName);
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
    public function nukeCollectionCommand($collectionName, $areYouSure)
    {
        $storageConnector = $this->getAkamaiStorageClientByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetClientByCollectionName($collectionName);

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
