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
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Proxy;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\RestrictedDirectory;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\StaticHost;
use Sitegeist\Flow\AkamaiNetStorage\Exception\FileDoesNotExistsException;
use Sitegeist\Flow\AkamaiNetStorage\FileUploadFailedException;

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
    ) {
    }

    /**
     * @param array<string, string|mixed> $options
     */
    public static function fromOptions(array $options): self
    {
        /* @phpstan-ignore-next-line */
        $host = Host::fromString((string) $options['host']);
        /* @phpstan-ignore-next-line */
        $staticHost = StaticHost::fromString((string) $options['staticHost']);
        /* @phpstan-ignore-next-line */
        $key = Key::create((string) $options['keyName'], (string) $options['key']);
        /* @phpstan-ignore-next-line */
        $cpCode = CpCode::fromString((string) $options['cpCode']);
        /* @phpstan-ignore-next-line */
        $restrictedDirectory = $options['restrictedDirectory'] ? RestrictedDirectory::fromString((string) $options['restrictedDirectory']) : null;
        /* @phpstan-ignore-next-line */
        $proxy = isset($options['proxy']) ? Proxy::create((string) $options['proxy']['http'] ?? null, (string) $options['proxy']['https'] ?? null) :
            null;
        /* @phpstan-ignore-next-line */
        $workingDirectory = $options['workingDirectory'] ? Path::fromString((string) $options['workingDirectory']) : null;

        return new self($host, $staticHost, $key, $cpCode, $restrictedDirectory, $proxy, $workingDirectory);
    }

    public function withWorkingDirectory(Path $path): self
    {
        $clone = clone $this;
        $clone->workingDirectory = $path;
        return $clone;
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
            /** @phpstan-ignore-next-line */
            $response = $this->httpClient->get($this->buildUriPathFromFilePath($path), [
                'headers' => [
                    'X-Akamai-ACS-Action' => Action::fromString('stat')->acsActionHeader()
                ]
            ]);
        } catch (\Exception $exception) {
            if ($exception->getCode() === 404) {
                throw new FileDoesNotExistsException(
                    sprintf('Akamai file object "%s" does not exist', (string) $path)
                );
            }
            return null;
        }

        return Stat::fromXml((string) $response->getBody());
    }

    public function upload(Path $path, string $content): ?Stat
    {
        $this->initialize();
        try {
            /** @phpstan-ignore-next-line */
            $this->httpClient->put($this->buildUriPathFromFilePath($path), [
                'headers' => [
                    'X-Akamai-ACS-Action' => Action::fromString('upload')->acsActionHeader(),
                    'body' => (string) $content
                ]
            ]);

            return $this->stat($path);
        } catch (\Exception $exception) {
            throw new FileUploadFailedException();
        }
    }

    public function dir(Path $path, bool $recursive = false): ?DirectoryListing
    {
        $this->initialize();

        try {
            /** @phpstan-ignore-next-line */
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
                    $recursiveFiles = $this->dir($file->path, true);
                    if ($recursiveFiles === null || $recursiveFiles->files === []) {
                        continue;
                    }
                    $files[] = $file->withChildren($recursiveFiles->files);
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
            /** @phpstan-ignore-next-line */
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
            /** @phpstan-ignore-next-line */
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

    /**
     * @return resource
     */
    public function stream(Path $path)
    {
        /** @phpstan-ignore-next-line */
        $response = $this->httpClient->get($this->buildUriPathFromFilePath($path), [
            'headers' => [
                'X-Akamai-ACS-Action' => Action::fromString('download')->acsActionHeader(),
            ]
        ]);

        $stream = StreamWrapper::getResource($response->getBody());
        fseek($stream, 0);

        return $stream;
    }

    public function getFullPath(): Path
    {
        return Path::fromString((string) $this->cpCode)
            ->append(Path::fromString((string) $this->restrictedDirectory))
            ->append($this->workingDirectory ?? Path::fromString(''));
    }

    protected function buildUriPathFromFilePath(Path $path): string
    {
        return urlencode($this->applyPathPrefix($path));
    }

    protected function applyPathPrefix(Path $path): Path
    {
        return Path::fromString((string) $this->cpCode)
            ->append(Path::fromString((string) $this->restrictedDirectory))
            ->append($this->workingDirectory ? $path->append($this->workingDirectory) : $path);
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $options = [];
        $options['base_uri'] = $this->host->toUri();

        if ($this->proxy instanceof Proxy) {
            $options['proxy'] = $this->proxy->toArray();
        }

        $authenticationHandler = Authentication::withSigner(
            Signer::create($this->key)
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
