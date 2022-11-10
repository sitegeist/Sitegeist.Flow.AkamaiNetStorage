<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Stat
{
    const TYPE_DIRECTORY = 'dir';
    const TYPE_FILE = 'file';
    const TYPE_SYMLINK = 'symlink';

    protected function __construct(
        public string $path,
        public string $type,
        public string $name,
        public int $mtime
    ) {
    }

    public static function fromXml(string $xml): self
    {
        $data = simplexml_load_string($xml);

        if ($data === false) {
            throw new \InvalidArgumentException('Given XML string is malformed and can not be parsed');
        }

        $path = (string) $data['directory'];
        $type = $data->file['type'];
        $name = $data->file['name'];
        $mtime = (int) $data->file['mtime'];

        return new self($path, $type, $name, $mtime);
    }

    public function isDirectory(): bool
    {
        return $this->type === self::TYPE_DIRECTORY;
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function isSymlink(): bool
    {
        return $this->type === self::TYPE_SYMLINK;
    }
}
