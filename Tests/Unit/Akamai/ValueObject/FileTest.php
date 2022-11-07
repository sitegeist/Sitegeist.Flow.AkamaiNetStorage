<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Tests\Unit\Akamai\ValueObject;

use PHPUnit\Framework\TestCase;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\File;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;

final class FileTest extends TestCase
{
    /**
     * @test
     */
    public function newFileDoesNotHaveChildrenByDefault()
    {
        $path = Path::fromString('/path/with/folder');
        $file = File::create(
            $path,
            File::TYPE_DIRECTORY,
            'pictures',
            1234567890
        );

        self::assertFalse($file->hasChildren());
    }

    /**
     * @test
     */
    public function withChildrenReturnsOriginalObjectWithChildren()
    {
        $path = Path::fromString('/path/with/folder/deeper');
        $file = File::create(
            $path,
            File::TYPE_DIRECTORY,
            'pictures',
            1234567890
        );

        $counter = 0;
        $children = [];
        do {
            $newPath = 'child ' . $counter;
            $children[] = File::create(
                $path->append(Path::fromString($newPath)),
                File::TYPE_DIRECTORY,
                $newPath,
                1234567890
            );
            $counter++;
        } while ($counter < 10);

        $file = $file->withChildren($children);
        self::assertTrue($file->hasChildren());
        self::assertEquals(10, count($file->children));
    }

}
