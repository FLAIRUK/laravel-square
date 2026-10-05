<?php

namespace FLAIRUK\Square\Http;

use FLAIRUK\Square\Exceptions\NetworkException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that sends the Square SDK's requests through Laravel's HTTP
 * client, so Http::fake(), Http::preventStrayRequests() and the HTTP client
 * events work with Square calls.
 */
final readonly class LaravelHttpClient implements ClientInterface
{
    public function __construct(
        private Factory $http,
        private int $timeout = 30,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $line = implode(', ', $values);

            if ($line !== '') {   // an empty header means "don't send it"
                $headers[$name] = $line;
            }
        }

        $pending = $this->http->timeout($this->timeout)->withHeaders($headers);

        $body = (string) $request->getBody();

        if ($body !== '') {
            $pending = $pending->withBody($body, $request->getHeaderLine('Content-Type') ?: 'application/json');
        }

        try {
            $response = $pending->send($request->getMethod(), (string) $request->getUri())->toPsrResponse();
        } catch (ConnectionException $e) {
            throw new NetworkException($request, $e->getMessage(), $e);
        }

        // The SDK reads the body with getContents(), from the current position.
        // Laravel may already have read it (and a faked response is shared
        // between requests), so start from the beginning.
        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }

        return $response;
    }
}
