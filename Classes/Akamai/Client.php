<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\StreamWrapper;
use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Client as GuzzleClient;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\DirectoryListing;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\File;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Stat;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Action;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\CpCode;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Host;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Key;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\MetaData;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Proxy;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\RestrictedDirectory;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\StaticHost;
use Sitegeist\Flow\AkamaiNetStorage\Exception\FileDoesNotExistsException;

#[Flow\Proxy(false)]
final class Client
{
    private bool $initialized = false;
    private ?GuzzleClient $httpClient = null;

    protected function __construct(
        protected Host $host,
        public StaticHost $staticHost,
        protected Key $key,
        protected CpCode $cpCode,
        protected ?RestrictedDirectory $restrictedDirectory = null,
        protected ?Proxy $proxy = null,
        protected ?Path $workingDirectory = null
    ) {}

    public static function fromOptions(array $options): self
    {
        $host = Host::fromString($options['host']);
        $staticHost = StaticHost::fromString($options['staticHost']);
        $key = Key::create($options['keyName'], $options['key']);
        $cpCode = CpCode::fromString($options['cpCode']);
        $restrictedDirectory = $options['restrictedDirectory'] ? RestrictedDirectory::fromString($options['restrictedDirectory']) : null;
        $proxy = isset($options['proxy']) ?
            Proxy::create($options['proxy']['http'] ?? null, $options['proxy']['https'] ?? null) :
            null;

        return new self($host, $staticHost, $key, $cpCode, $restrictedDirectory, $proxy);
    }

    public function canConnect(): bool
    {
        $this->initialize();
        try {
            $this->stat(Path::root());
            return true;
        } catch (FileDoesNotExistsException $exception) {
            return false;
        }
    }

    public function stat(Path $path): ?Stat
    {
        $this->initialize();
        try {
            $response = $this->httpClient->get($this->buildUriPathFromFilePath($path), [
                'headers' => [
                    'X-Akamai-ACS-Action' => Action::fromString('stat')->acsActionHeader()
                ]
            ]);
        } catch (\Exception $exception) {
            if ($exception->getCode() === 404) {
                throw new FileDoesNotExistsException(
                    sprintf('Akamai file object "%s" does not exsists', (string) $path)
                );
            }
            return null;
        }

        $xml = simplexml_load_string((string) $response->getBody());

        return Stat::fromXml($xml);
    }

    public function dir(Path $path, bool $recursive = false): ?DirectoryListing
    {
        $this->initialize();

        try {
            $response = $this->httpClient->get($this->buildUriPathFromFilePath($path), [
                'headers' => [
                    'X-Akamai-ACS-Action' => Action::fromString('dir')->acsActionHeader()
                ]
            ]);
        } catch (\Exception $exception) {
            return null;
        }

        $directoryListing = DirectoryListing::fromXml((string) $response->getBody());

        if ($recursive === true) {
            $files = [];
            /** @var File $file */
            foreach ($directoryListing->files as $file) {
                if ($file->isDirectory()) {
                    $children[] = $this->dir($file->path, true)->files;
                    $files[] = $file->withChildren($children);
                } else {
                    $files[] = $file;
                }
            }
            $directoryListing = DirectoryListing::fromFiles($path, $files);
        }

        return $directoryListing;

    }

    public function delete(Path $path): bool
    {
        $this->initialize();

        try {
            $this->httpClient->put($this->buildUriPathFromFilePath($path), [
                'headers' => [
                    'X-Akamai-ACS-Action' => Action::fromString('delete')->acsActionHeader()
                ]
            ]);

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function rmdir(Path $path): bool
    {
        $this->initialize();

        try {
            $this->httpClient->put($this->buildUriPathFromFilePath($path), [
                'headers' => [
                    'X-Akamai-ACS-Action' => Action::fromString('rmdir')->acsActionHeader()
                ]
            ]);

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function stream(Path $path)
    {
        $response = $this->httpClient->get($this->applyPathPrefix($path), [
            'headers' => [
                'X-Akamai-ACS-Action' => Action::fromString('download')->acsActionHeader(),
            ]
        ]);

        $stream = StreamWrapper::getResource($response->getBody());
        fseek($stream, 0);

        return $stream;
    }

    protected function buildUriPathFromFilePath(Path $path): string
    {
        return urlencode($this->applyPathPrefix($path));
    }

    protected function applyPathPrefix(Path $path): Path
    {
        return Path::fromString((string) $this->cpCode)
            ->append(Path::fromString((string) $this->restrictedDirectory))
            ->append($path);

    }

    private function initialize()
    {
        if ($this->initialized) {
            return;
        }
        $options = [];
        $options['base_uri'] = $this->host->toUri();

        if ($this->proxy instanceof Proxy) {
            $options['proxy'] = $this->proxy->toArray();
        }

        $signer = new Signer();
        $authenticationHandler = Authentication::withSigner(
            $signer->withKey($this->key)
        );
        $stack = HandlerStack::create();
        $stack->push($authenticationHandler, 'authentication-handler');

        $options['handler'] = $stack;

        $this->httpClient = new GuzzleClient(
            $options
        );
        $this->initialized = true;
    }
}
