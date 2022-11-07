<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Tests\Unit\Akamai\ValueObject;

use PHPUnit\Framework\TestCase;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;

final class PathTest extends TestCase
{
    /**
     * @test
     */
    public function returnedPathRemovesSlashesAtBeginningAndEnd()
    {
        $path = Path::fromString('/path/with/folder');
        self::assertEquals('path/with/folder', (string) $path);
    }

    /**
     * @test
     */
    public function rootPathMethodReturnsBlankPath()
    {
        self::assertEquals('', (string) Path::root());
    }

    /**
     * @test
     */
    public function appendPathAppendsPathAsExpected()
    {
        $path = Path::fromString('/path/with/folder');

        self::assertEquals('path/with/folder/file.pdf', (string) $path->append(Path::fromString('file.pdf')));
        self::assertEquals('path/with/folder/deeper/file.pdf', (string) $path->append(Path::fromString('deeper/file.pdf')));


    }
}
