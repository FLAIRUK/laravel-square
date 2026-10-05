<?php

namespace FLAIRUK\Square\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Square could not be reached. The SDK retries these and then rethrows them
 * wrapped in \Square\Exceptions\SquareException.
 */
class NetworkException extends SquareException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
