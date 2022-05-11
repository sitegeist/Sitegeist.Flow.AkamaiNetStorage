<?php
namespace Sitegeist\Flow\AkamaiNetStorage\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class FileStoreAdapterAspect
{
    /**
     * @Flow\Around("method(Akamai\NetStorage\FileStoreAdapter->handleFileMetaData())")
     * @param JoinPointInterface $joinPoint
     * @return array
     */
    public function removeLeadingSlash(JoinPointInterface $joinPoint)
    {
        $metadata = $joinPoint->getAdviceChain()->proceed($joinPoint);

        if (strpos($metadata['path'], '/') === 0) {
            $metadata['path'] = substr($metadata['path'], 1);
        }

        return $metadata;
    }

    /**
     * @Flow\Around("method(Akamai\NetStorage\FileStoreAdapter->listContents())")
     * @param JoinPointInterface $joinPoint
     * @return array
     */
    public function noRecursive(JoinPointInterface $joinPoint)
    {
        $directory = $joinPoint->getMethodArgument('directory');
        $recursive = $joinPoint->getMethodArgument('recursive');

        $response = $this->httpClient->get($this->applyPathPrefix($directory), [
            'headers' => [
                'X-Akamai-ACS-Action' => $this->getAcsActionHeaderValue('dir')
            ]
        ]);
        $xml = simplexml_load_string((string) $response->getBody());

        $baseDir = (string) $xml['directory'];
        $dir = [];
        foreach ($xml->file as $file) {
            $meta = $this->handleFileMetaData($directory, $file);
            $dir[$meta['path']] = $meta;
            if ($recursive || $meta['type'] == 'dir') {
                $dir[$meta['path']]['children'] = $this->listContents($meta['path'], $recursive);
            }
        }

        return $dir;
    }
}
