<?php

namespace Sitegeist\Flow\AkamaiNetStorage;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\Exception;
use Neos\Flow\ResourceManagement\Publishing\MessageCollector;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceMetaDataInterface;
use Neos\Flow\ResourceManagement\Storage\StorageObject;
use Neos\Flow\ResourceManagement\Target\TargetInterface;
use Neos\Utility\Arrays;
use Psr\Log\LoggerInterface;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Filename;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;
use Sitegeist\Flow\AkamaiNetStorage\Exception\FileDoesNotExistsException;

/**
 * A resource publishing target based on Akamai NetStorage
 */
class AkamaiTarget implements TargetInterface
{
    use AkamaiClientTrait;

    /**
     * Name which identifies this resource target
     *
     * @var string
     */
    protected $name;

    /**
     * Target options
     *
     * @var array<string, mixed>
     */
    protected $options;

    /**
     * Internal cache for known storages, indexed by storage name
     *
     * @var array<\Neos\Flow\ResourceManagement\Storage\StorageInterface>
     */
    protected $storages = array();

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * @Flow\Inject
     * @var MessageCollector
     */
    protected $messageCollector;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Constructor
     *
     * @param string $name Name of this target instance, according to the resource settings
     * @param array<string, mixed> $options Options for this target
     * @throws Exception
     */
    public function __construct($name, array $options = array())
    {
        $this->name = $name;
        $this->options = $options;
    }

    /**
     * Returns the name of this target instance
     *
     * @return string The target instance name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Publishes the whole collection to this target
     *
     * @param \Neos\Flow\ResourceManagement\CollectionInterface $collection The collection to publish
     * @param callable $callback Function called after each resource publishing
     * @return void
     * @throws Exception
     */
    public function publishCollection(CollectionInterface $collection, callable $callback = null)
    {

        foreach ($collection->getObjects() as $object) {
            $path = $this->getRelativePublicationPathAndFilename($object);
            /**
             * @var resource|false $sourceStream
             */
            $sourceStream = $object->getStream();
            if ($sourceStream === false) {
                $message = sprintf('Could not publish resource with path %s of collection %s because there seems to be no corresponding data in the storage.', $path, $collection->getName());
                $this->messageCollector->append($message);
                continue;
            }

            /** @var \Neos\Flow\ResourceManagement\Storage\StorageObject $object */
            $this->publishFile($sourceStream, $path, $object);
        }
    }

    /**
     * Returns the web accessible URI pointing to the given static resource
     *
     * @param string $relativePathAndFilename Relative path and filename of the static resource
     * @return string The URI
     */
    public function getPublicStaticResourceUri($relativePathAndFilename)
    {
        $client = $this->getClient($this->name, $this->options);
        $path = Path::fromString($this->encodeRelativePathAndFilenameForUri($relativePathAndFilename));
        return 'https://' . $client->staticHost . '/' . $client->buildPublicUriPath($path);
    }

    /**
     * Publishes the given persistent resource from the given storage
     *
     * @param \Neos\Flow\ResourceManagement\PersistentResource $resource The resource to publish
     * @param CollectionInterface $collection The collection the given resource belongs to
     * @return void
     * @throws Exception
     */
    public function publishResource(PersistentResource $resource, CollectionInterface $collection)
    {
        // TODO: check not to puplish to storage directory
        $storage = $collection->getStorage();

        // If we use Akamai as a target and a storage ...
        if ($storage instanceof AkamaiStorage) {
            // ... we need to make sure not to publish into the storage workingDir
            if (
                $storage->getFullPath()->equals(
                    $this->getClient($this->name, $this->options)->getFullPath()
                )
            ) {
                throw new Exception(sprintf('Could not publish resource with SHA1 hash %s of collection %s because publishing to the storage workDir is not allowed. Choose a different workingDir for your target.', $resource->getSha1(), $collection->getName()), 1428929563);
            };

            // TODO: performance improvement if storage and target share the same host, cpCode and restricted directory
        }

        $sourceStream = $resource->getStream();
        if ($sourceStream === false) {
            $message = sprintf('Could not publish resource with SHA1 hash %s of collection %s because there seems to be no corresponding data in the storage.', $resource->getSha1(), $collection->getName());
            $this->messageCollector->append($message);
            return;
        }
        /* @phpstan-ignore-next-line */
        $this->publishFile($sourceStream, $this->getRelativePublicationPathAndFilename($resource), $resource);
    }

    /**
     * Unpublishes the given persistent resource
     *
     * @param \Neos\Flow\ResourceManagement\PersistentResource $resource The resource to unpublish
     * @return void
     */
    public function unpublishResource(PersistentResource $resource)
    {
        $client = $this->getClient($this->name, $this->options);
        $encodedRelativeTargetPathAndFilename = $this->encodeRelativePathAndFilenameForUri($this->getRelativePublicationPathAndFilename($resource));

        try {
            // delete() returns boolean
            $client->delete(Path::fromString($resource->getRelativePublicationPath()), Filename::fromString($resource->getFilename()));
        } catch (FileDoesNotExistsException $exception) {
        }
    }

    /**
     * Returns the web accessible URI pointing to the specified persistent resource
     *
     * @param \Neos\Flow\ResourceManagement\PersistentResource $resource Resource object or the resource hash of the resource
     * @return string The URI
     */
    public function getPublicPersistentResourceUri(PersistentResource $resource)
    {
        $encodedRelativeTargetPathAndFilename = $this->encodeRelativePathAndFilenameForUri($this->getRelativePublicationPathAndFilename($resource));
        $client = $this->getClient($this->name, $this->options);
        $path = Path::fromString($encodedRelativeTargetPathAndFilename);
        return 'https://' . $client->staticHost . '/' . $client->buildPublicUriPath($path);
    }

    /**
     * Publishes the specified source file to this target, with the given relative path.
     *
     * @param resource $sourceStream
     * @param string $relativeTargetPathAndFilename
     * @param ResourceMetaDataInterface $metaData
     * @throws \Exception
     */
    protected function publishFile($sourceStream, $relativeTargetPathAndFilename, ResourceMetaDataInterface $metaData): void
    {
        $client = $this->getClient($this->name, $this->options);
        $encodedRelativeTargetPathAndFilename = $this->encodeRelativePathAndFilenameForUri($relativeTargetPathAndFilename);

        try {
            $content = stream_get_contents($sourceStream);
            if ($content === false) {
                throw new FileDoesNotExistsException();
            }
            $client->upload(Path::fromString($encodedRelativeTargetPathAndFilename), $content);
            $this->systemLogger->debug(sprintf('Successfully published resource as object "%s" with Sha1 "%s"', $relativeTargetPathAndFilename, $metaData->getSha1() ?: 'unknown'));
        } catch (\Exception $e) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            if (!$e instanceof FileDoesNotExistsException) {
                $this->systemLogger->debug(sprintf('Failed publishing resource as object "%s" with Sha1 hash "%s": %s', $relativeTargetPathAndFilename, $metaData->getSha1() ?: 'unknown', $e->getMessage()));
                throw $e;
            }
        }
    }

    /**
     * Determines and returns the relative path and filename for the given Storage Object or Resource. If the given
     * object represents a persistent resource, its own relative publication path will be empty. If the given object
     * represents a static resources, it will contain a relative path.
     *
     * @param ResourceMetaDataInterface $object Resource or Storage Object
     * @return string The relative path and filename, for example "c828d0f88ce197be1aff7cc2e5e86b1244241ac6/MyPicture.jpg"
     */
    protected function getRelativePublicationPathAndFilename(ResourceMetaDataInterface $object)
    {
        if ($object->getRelativePublicationPath() !== '') {
            $pathAndFilename = $object->getRelativePublicationPath() . $object->getFilename();
        } else {
            $pathAndFilename = $object->getSha1() . '/' . $object->getFilename();
        }
        return $pathAndFilename;
    }

    /**
     * Applies rawurlencode() to all path segments of the given $relativePathAndFilename
     *
     * @param string $relativePathAndFilename
     * @return string
     */
    protected function encodeRelativePathAndFilenameForUri($relativePathAndFilename)
    {
        return implode('/', array_map('rawurlencode', explode('/', $relativePathAndFilename)));
    }
}
