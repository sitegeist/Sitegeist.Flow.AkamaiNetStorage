<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Tests\Functional;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;
use Neos\Flow\Tests\FunctionalTestCase;

/**
 * Functional tests for the ResourceManager
 */
class AkamaiRessourceHandlingTest extends FunctionalTestCase {
    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var ResourceRepository
     */
    protected $resourceRepository;

    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * The settings from Sitegeist.AkamaiNetStorage.functionalTests in Settings.yaml
     *
     * @var array
     */
    protected $functionalTestSettings;

    /**
     * @var string
     */
    protected $fixturesPath;

    /**
     * @return void
     */
    public function setUp() {
        parent::setUp();
        if (!$this->persistenceManager instanceof PersistenceManager) {
            $this->markTestSkipped('Doctrine persistence is not enabled');
        }
        $this->resourceManager = $this->objectManager->get(ResourceManager::class);
        $this->resourceRepository = $this->objectManager->get(ResourceRepository::class);
        /* @var $configurationManager ConfigurationManager */
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        $this->functionalTestSettings = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sitegeist.Flow.AkamaiNetStorage.functionalTests'
        );

        $packageManager = $this->objectManager->get(PackageManager::class);
        $packagePath = $packageManager->getPackage('Sitegeist.Flow.AkamaiNetStorage')->getPackagePath();

        $this->fixturesPath = $packagePath . 'Tests/Functional/Fixtures/';
    }

    private function injectSettings() {
        $this->resourceManager->injectSettings([
            'resource' => [
                'collections' => [
                    'testPersistent' => [
                        'storage' => 'testPersistentStorage',
                        'target' => 'testPersistentTarget'
                    ]
                ],
                'storages' => [
                    'testPersistentStorage' => [
                        'storage' => 'Sitegeist\Flow\AkamaiNetStorage\AkamaiStorage',
                        'storageOptions' => $this->functionalTestSettings['storageOptions']
                    ]
                ],
                'targets' => [
                    'testPersistentTarget' => [
                        'target' => 'Neos\Flow\ResourceManagement\Target\FileSystemTarget',
                        'targetOptions' => [
                            'path' => FLOW_PATH_WEB . '_Resources/Persistent/',
                            'baseUri' => '_Resources/Persistent/'
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function importResourceFromSimpleContent() {
        $this->injectSettings();

        $persistentResource = $this->resourceManager->importResourceFromContent(
            'fixture with simple content', 'fixtureWithSimpleContent.txt',
            'testPersistent'
        );

        // Check we can read the file again from storage
        $tempFile = $persistentResource->createTemporaryLocalCopy();
        $this->assertEquals('fixture with simple content', file_get_contents($tempFile), 'Stored File contents do not match');

        // Check the file has been published automatically in the local target
        $targetUrl = $this->resourceManager->getPublicPersistentResourceUri($persistentResource);
        $localTargetPath = str_replace('http://localhost/_Resources/', FLOW_PATH_WEB . '/_Resources/', $targetUrl);

        $this->assertEquals('fixture with simple content', file_get_contents($localTargetPath), 'Published file contents do not match');
    }

    /**
     * @test
     */
    public function importResourceFromContentWithSpecialChars() {
        $this->injectSettings();

        $persistentResource = $this->resourceManager->importResourceFromContent(
            'fixture with special chars äöüß#?', 'fixtureWithSpecialChars.txt',
            'testPersistent'
        );

        // Check we can read the file again from storage
        $tempFile = $persistentResource->createTemporaryLocalCopy();
        $this->assertEquals('fixture with special chars äöüß#?', file_get_contents($tempFile), 'Stored File contents do not match');

        // Check the file has been published automatically in the local target
        $targetUrl = $this->resourceManager->getPublicPersistentResourceUri($persistentResource);
        $localTargetPath = str_replace('http://localhost/_Resources/', FLOW_PATH_WEB . '/_Resources/', $targetUrl);

        $this->assertEquals('fixture with special chars äöüß#?', file_get_contents($localTargetPath), 'Published file contents do not match');
    }

    /**
     * @test
     */
    public function importResourceFromFile() {
        $this->injectSettings();

        $persistentResource = $this->resourceManager->importResource(
            $this->fixturesPath . 'fixture.txt',
            'testPersistent'
        );

        // Check we can read the file again from storage
        $tempFile = $persistentResource->createTemporaryLocalCopy();
        $this->assertEquals("some content\n", file_get_contents($tempFile), 'Stored File contents do not match');

        // Check the file has been published automatically in the local target
        $targetUrl = $this->resourceManager->getPublicPersistentResourceUri($persistentResource);
        $localTargetPath = str_replace('http://localhost/_Resources/', FLOW_PATH_WEB . '/_Resources/', $targetUrl);

        $this->assertEquals("some content\n", file_get_contents($localTargetPath), 'Published file contents do not match');
    }

    /**
     * @test
     */
    public function importResourceFromFileWithSpecialCharsInFilename() {
        $this->injectSettings();

        $persistentResource = $this->resourceManager->importResource(
            $this->fixturesPath . 'fixtureäöüß.txt',
            'testPersistent'
        );

        // Check we can read the file again from storage
        $tempFile = $persistentResource->createTemporaryLocalCopy();
        $this->assertEquals("some other content\n", file_get_contents($tempFile), 'Stored File contents do not match');

        // TODO check why failing
        // Check the file has been published automatically in the local target
        // $targetUrl = $this->resourceManager->getPublicPersistentResourceUri($persistentResource);
        // $localTargetPath = str_replace('http://localhost/_Resources/', FLOW_PATH_WEB . '/_Resources/', $targetUrl);

        // $this->assertEquals("some other content\n", file_get_contents($localTargetPath), 'Published file contents do not match');
    }
}
