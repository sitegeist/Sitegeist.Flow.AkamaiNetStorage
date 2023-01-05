<?php

namespace Sitegeist\Flow\AkamaiNetStorage;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\Storage\Exception as StorageException;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;
use Neos\Flow\ResourceManagement\Storage\Exception;
use Neos\Flow\ResourceManagement\Storage\WritableStorageInterface;
use Neos\Flow\Utility\Environment;
use Psr\Log\LoggerInterface;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Filename;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;
use Sitegeist\Flow\AkamaiNetStorage\Exception\FileDoesNotExistsException;

/**
 * A resource storage based on Akamai NetStorage
 */
class AkamaiStorage implements WritableStorageInterface
{
    use AkamaiClientTrait;

    /**
     * Name which identifies this resource storage
     *
     * @var string
     */
    protected $name;

    /**
     * @var array<string, mixed>
     */
    protected $options;

    /**
     * @Flow\Inject
     * @var Environment
     */
    protected $environment;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var ResourceRepository
     */
    protected $resourceRepository;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * Constructor
     *
     * @param string $name Name of this storage instance, according to the resource settings
     * @param array<string, mixed> $options Options for this storage
     * @throws Exception
     */
    public function __construct($name, array $options = array())
    {
        $this->name = $name;
        $this->options = $options;
    }

    /**
     * Returns the instance name of this storage
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Imports a resource (file) from the given URI or PHP resource stream into this storage.
     *
     * On a successful import this method returns a Resource object representing the newly
     * imported persistent resource.
     *
     * @param string | resource $source The URI (or local path and filename) or the PHP resource stream to import the resource from
     * @param string $collectionName Name of the collection the new Resource belongs to
     * @return PersistentResource A resource object representing the imported resource
     * @throws \Neos\Flow\ResourceManagement\Storage\Exception
     */
    public function importResource($source, $collectionName)
    {
        $temporaryTargetPathAndFilename = $this->environment->getPathToTemporaryDirectory() . uniqid('Sitegeist_AkamaiNetstorage_');

        if (is_resource($source)) {
            try {
                $target = fopen($temporaryTargetPathAndFilename, 'wb');
                if ($target === false) {
                    throw new StorageException(sprintf('The target path "%s" is not writable', $temporaryTargetPathAndFilename));
                }
                stream_copy_to_stream($source, $target);
                fclose($target);
            } catch (\Exception $e) {
                throw new Exception(sprintf('Could import the content stream to temporary file "%s".', $temporaryTargetPathAndFilename), 1550653595);
            }
        } else {
            try {
                copy($source, $temporaryTargetPathAndFilename);
            } catch (\Exception $e) {
                throw new Exception(sprintf('Could not copy the file from "%s" to temporary file "%s".', $source, $temporaryTargetPathAndFilename), 1550653610);
            }
        }

        $persistentResource = $this->importTemporaryFile($temporaryTargetPathAndFilename, $collectionName);
        // clean up the temporary file
        unlink($temporaryTargetPathAndFilename);
        return $persistentResource;
    }

    /**
     * Imports a resource from the given string content into this storage.
     *
     * On a successful import this method returns a Resource object representing the newly
     * imported persistent resource.
     *
     * The specified filename will be used when presenting the resource to a user. Its file extension is
     * important because the resource management will derive the IANA Media Type from it.
     *
     * @param string $content The actual content to import
     * @return PersistentResource A resource object representing the imported resource
     * @param string $collectionName Name of the collection the new Resource belongs to
     * @return PersistentResource A resource object representing the imported resource
     * @throws Exception
     * @api
     */
    public function importResourceFromContent($content, $collectionName)
    {
        $sha1Hash = sha1($content);
        $filename = $sha1Hash;

        $resource = new PersistentResource();
        $resource->setFilename($filename);
        $resource->setFileSize(strlen($content));
        $resource->setCollectionName($collectionName);
        $resource->setSha1($sha1Hash);

        $client = $this->getClient($this->name, $this->options);
        try {
            // first checking if a resource exists, assuming it does
            $client->stat(Path::fromString($sha1Hash));
            $resourceAlreadyExists = true;
        } catch (FileDoesNotExistsException $exception) {
            // nope it does not
            $resourceAlreadyExists = false;
        }

        if (!$resourceAlreadyExists) {
            // write a resource to the storage
            $client->upload(Path::fromString($sha1Hash), (string) $content);
        }

        return $resource;
    }

    /**
     * Deletes the storage data related to the given Resource object
     *
     * @param \Neos\Flow\ResourceManagement\PersistentResource $resource The Resource to delete the storage data of
     * @return boolean TRUE if removal was successful
     * @api
     */
    public function deleteResource(PersistentResource $resource)
    {
        $client = $this->getClient($this->name, $this->options);

        try {
            // delete() returns boolean
            $wasDeleted = $client->delete($client->getFullPath(), Filename::fromString($resource->getSha1()));
        } catch (FileDoesNotExistsException $exception) {
            // In some rare cases the file might be missing in the storage but is still present in the db.
            // We need to process the corresponding exception to be able to also remove the resource from the db.
            $wasDeleted = true;
        }

        return $wasDeleted;
    }

    /**
     * Returns a stream handle which can be used internally to open / copy the given resource
     * stored in this storage.
     *
     * @param PersistentResource $resource The resource stored in this storage
     * @return resource | boolean The resource stream or false if the stream could not be obtained
     * @api
     */
    public function getStreamByResource(PersistentResource $resource)
    {
        $client = $this->getClient($this->name, $this->options);
        try {
            return $client->stream(Path::fromString($resource->getSha1()));
        } catch (\Exception $e) {
            $this->systemLogger->error(sprintf('Could not retrieve stream for resource %s', $resource->getSha1()));
            return false;
        }
    }

    /**
    /**
     * Returns a stream handle which can be used internally to open / copy the given resource
     * stored in this storage.
     *
     * @param string $relativePath A path relative to the storage root, for example "MyFirstDirectory/SecondDirectory/Foo.css"
     * @return resource | boolean A URI (for example the full path and filename) leading to the resource file or false if it does not exist
     * @throws Exception
     * @api
     */
    public function getStreamByResourcePath($relativePath)
    {
        throw new \RuntimeException('The method "getStreamByResourcePath" is not implemented by this storage driver, as it is not called from within Flow.');
    }


    /**
     * Retrieve all Objects stored in this storage.
     *
     * @return \Generator<\Neos\Flow\ResourceManagement\Storage\StorageObject>
     * @api
     */
    public function getObjects()
    {
        foreach ($this->resourceManager->getCollectionsByStorage($this) as $collection) {
            /* @phpstan-ignore-next-line */
            yield $this->getObjectsByCollection($collection);
        }
    }

    /**
     * Retrieve all Objects stored in this storage, filtered by the given collection name
     *
     * @param CollectionInterface $collection
     * @internal param string $collectionName
     * @return \Generator<\Neos\Flow\ResourceManagement\Storage\StorageObject>
     * @api
     */
    public function getObjectsByCollection(CollectionInterface $collection)
    {
        $iterator = $this->resourceRepository->findByCollectionNameIterator($collection->getName());
        foreach ($this->resourceRepository->iterate($iterator) as $resource) {
            /** @var \Neos\Flow\ResourceManagement\PersistentResource $resource */
            $object = new \Neos\Flow\ResourceManagement\Storage\StorageObject();
            $object->setFilename($resource->getFilename());
            $object->setSha1($resource->getSha1());
            $object->setStream(function () use ($resource) {
                return $this->getStreamByResource($resource);
            });
            yield $object;
        }
    }

    /**
     * Imports the given temporary file into the storage and creates the new resource object.
     *
     * @param string $temporaryPathAndFilename Path and filename leading to the temporary file
     * @param string $collectionName Name of the collection to import into
     * @return PersistentResource The imported resource
     */
    protected function importTemporaryFile($temporaryPathAndFilename, $collectionName)
    {
        $sha1Hash = sha1_file($temporaryPathAndFilename);

        $resource = new PersistentResource();
        $resource->setFileSize((int) filesize($temporaryPathAndFilename));
        $resource->setCollectionName($collectionName);
        $resource->setSha1((string) $sha1Hash);

        $client = $this->getClient($this->name, $this->options);
        try {
            // first checking if a resource exists, assuming it does
            $client->stat(Path::fromString((string) $sha1Hash));
            $resourceAlreadyExists = true;
        } catch (FileDoesNotExistsException $exception) {
            // nope it does not
            $resourceAlreadyExists = false;
        }

        if (!$resourceAlreadyExists) {
            // write new resource to storage
            $client->upload(Path::fromString((string) $sha1Hash), (string) file_get_contents($temporaryPathAndFilename));
        }

        return $resource;
    }

    public function getFullPath(): Path
    {
        return $this->getClient($this->name, $this->options)->getFullPath();
    }
}
