<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class File
{
    const TYPE_DIRECTORY = 'dir';
    const TYPE_FILE = 'file';
    const TYPE_SYMLINK = 'symlink';

    protected function __construct(
        public Path $path,
        public string $type,
        public string $name,
        public int $mtime,
        public ?int $files = null,
        public ?int $bytes = null,
        public ?int $size = null,
        public ?string $md5 = null,
        public array $children = []
    ) {
    }

    public static function create(Path $path, string $type, string $name, int $mtime): self
    {
        return new self($path, $type, $name, $mtime);
    }

    public function withChildren(array $children): self
    {
        $clone = clone $this;
        $clone->children = $children;

        return $clone;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
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

    public function fullPath(): Path
    {
        return $this->path->append(Path::fromString($this->name));
    }
}
