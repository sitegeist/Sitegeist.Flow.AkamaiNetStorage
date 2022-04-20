<?php
namespace Sitegeist\Flow\AkamaiNetStorage\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class AkamaiMetaDataAspect
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
}
