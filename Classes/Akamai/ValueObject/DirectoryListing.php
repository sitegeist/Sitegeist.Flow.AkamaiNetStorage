<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class DirectoryListing
{
    /**
     * @param Path $path
     * @param array<File> $files
     */
    protected function __construct(
        public Path $path,
        public array $files = [],
    ) {
    }

    public static function create(Path $path): self
    {
        return new self($path);
    }

    public function fromXml(string $xml): self
    {
        $data = simplexml_load_string($xml);

        if ($data === false) {
            throw new \InvalidArgumentException('Given XML string is malformed and can not be parsed');
        }

        $files = [];
        foreach ($data->file as $fileObject) {
            $file = File::create(
                $this->path,
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

        $this->files = $files;
        return $this;
    }

    /**
     * @param Path $path
     * @param array<File> $files
     * @return self
     */
    public static function fromFiles(Path $path, array $files): self
    {
        return new self($path, $files);
    }
}
