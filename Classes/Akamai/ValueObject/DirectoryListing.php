<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class DirectoryListing
{
    protected function __construct(
        public Path $path,
        public array $files = []
    ){}

    public static function fromXml(string $xml): self
    {
        $data = simplexml_load_string($xml);

        $path = Path::fromString($data['directory']);
        foreach ($data->file as $fileObject) {
            $file = File::create(
                $path,
                (string) $fileObject['type'],
                (string) $fileObject['name'],
                (int) $fileObject['mtime']
            );
            if ($file->isFile()) {
                $file->size = (int) $fileObject['size'];
                $file->md5 = (string) $fileObject['md5'];
            }
            if ($file->isDirectory()) {
                $file->files = (int) $fileObject['files'];
                $file->bytes = (int) $fileObject['bytes'];
            }

            $files[] = $file;
        }

        return new self($path, $files);
    }

    public static function fromFiles(Path $path, array $files): self
    {
        return new self($path, $files);
    }
}
