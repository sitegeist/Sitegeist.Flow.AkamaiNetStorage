<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Akamai;

use GuzzleHttp\Promise\Create;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

#[Flow\Proxy(false)]
final class Logging
{
    protected function __construct(
        protected LoggerInterface $logger
    ) {
    }

    public static function withLogger(LoggerInterface $logger): self
    {
        return new self($logger);
    }

    /**
     * Returns a function which is handled when a request was successful.
     *
     * @param RequestInterface $request
     *
     * @return callable
     */
    protected function onSuccess(RequestInterface $request)
    {
        return function (ResponseInterface $response) use ($request) {
            $this->logger->debug('AKAMAI: ' . $request->getMethod() . ' ' . $request->getUri() . ' -> ' . $response->getStatusCode());
            return $response;
        };
    }

    /**
     * Returns a function which is handled when a request was rejected.
     *
     * @param RequestInterface $request
     *
     * @return callable
     */
    protected function onFailure(RequestInterface $request)
    {
        return function ($reason) use ($request) {
            $this->logger->debug('AKAMAI: ' . $request->getMethod() . ' ' . $request->getUri() . ' !! ' . $reason);
            return Create::rejectionFor($reason);
        };
    }

    /**
     * Called when the middleware is handled by the client.
     *
     * @param callable $handler
     *
     * @return callable
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                $this->onSuccess($request),
                $this->onFailure($request)
            );
        };
    }
}
