<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Tests\Unit\Akamai\ValueObject;

use PHPUnit\Framework\TestCase;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Action;

final class ActionTest extends TestCase
{
    /**
     * @test
     */
    public function returnedActionHeaderContainsVersion()
    {
        $action = Action::fromString('list');
        self:self::assertEquals('version=1&action=list', $action->acsActionHeader());
    }

    /**
     * @test
     */
    public function actionHeadersWithRequiredFormatContainsVersionAndFormat()
    {
        $action = Action::fromString('download');
        self:self::assertEquals('version=1&action=download&format=xml', $action->acsActionHeader());
    }
}
