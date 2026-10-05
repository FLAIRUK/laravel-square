<?php

namespace FLAIRUK\Square\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A webhook request had a missing or invalid x-square-hmacsha256-signature header.
 *
 * Rendered by Laravel as a 403 response, and not logged.
 */
class InvalidSignatureException extends SquareException implements HttpExceptionInterface, ShouldntReport
{
    public static function missing(): self
    {
        return new self('The Square webhook signature header is missing.');
    }

    public static function invalid(): self
    {
        return new self('The Square webhook signature is invalid.');
    }

    public function getStatusCode(): int
    {
        return 403;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
