<?php

namespace Sitegeist\Flow\AkamaiNetStorage;

use \Akamai\NetStorage\FileStoreAdapter as AkamaiFileStoreAdapter;

class FileStoreAdapter extends AkamaiFileStoreAdapter
{
    protected function handleFileMetaData($baseDir, $file = null)
    {
        $metadata = parent::handleFileMetaData($baseDir, $file);

        if (strpos($metadata['path'], '/') === 0) {
            $metadata['path'] = substr($metadata['path'], 1);
        }

        return $metadata;
    }

    public function listContents($directory = '', $recursive = false)
    {
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
            if ($recursive && $meta['type'] == 'dir') {
                $dir[$meta['path']]['children'] = $this->listContents($meta['path'], $recursive);
            }
        }

        return $dir;
    }


}
