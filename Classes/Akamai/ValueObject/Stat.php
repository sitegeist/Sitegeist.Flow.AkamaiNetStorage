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
        public Path $path,
        public string $type,
        public string $name,
        public int $mtime,
        public ?string $md5 = null
    ) {
    }

    public static function fromXml(Path $path, string $xml): self
    {
        $data = simplexml_load_string($xml);

        if ($data === false) {
            throw new \InvalidArgumentException('Given XML string is malformed and can not be parsed');
        }

        $type = $data->file['type'];
        $name = $data->file['name'];
        $mtime = (int) $data->file['mtime'];
        $md5 = $data->file['md5'] ?: null;

        return new self($path, $type, $name, $mtime, $md5);
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
