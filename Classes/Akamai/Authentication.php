<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\RequestInterface;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Action;
use Sitegeist\Flow\AkamaiNetStorage\Akamai\ValueObject\Path;

#[Flow\Proxy(false)]
final class Authentication
{
    protected function __construct(
        protected Signer $signer
    ) {
    }

    public static function withSigner(Signer $signer): self
    {
        return new self($signer);
    }

    public function __invoke(callable $handler): \Closure
    {
        return function (
            RequestInterface $request,
            array $config
        ) use ($handler) {
            $action = $request->getHeader('X-Akamai-ACS-Action');

            if (sizeof($action) === 0) {
                return $handler($request, $config);
            }

            $signer = clone $this->signer;
            $signer = $signer->withPath(Path::fromString($request->getUri()->getPath()));
            $signer = $signer->withAction(Action::fromString($action[0]));

            foreach ($signer->authenticationHeaders() as $header => $value) {
                $request = $request->withHeader($header, $value);
            }

            return $handler($request, $config);
        };
    }
}
