<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Tests\Unit\Akamai\ValueObject;

use Neos\Flow\Tests\UnitTestCase;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Action;

final class ActionTest extends UnitTestCase
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
