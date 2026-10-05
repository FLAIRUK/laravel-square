<?php

namespace FLAIRUK\Square\Http\Middleware;

use Closure;
use FLAIRUK\Square\Exceptions\InvalidSignatureException;
use FLAIRUK\Square\Webhooks\WebhookSignature;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests without a valid x-square-hmacsha256-signature header (403).
 *
 * The signature covers the notification URL, taken from SQUARE_WEBHOOK_URL,
 * or from the request if that isn't set.
 */
class VerifyWebhookSignature
{
    public function __construct(protected readonly WebhookSignature $signature) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header(WebhookSignature::HEADER);

        if (blank($header)) {
            throw InvalidSignatureException::missing();
        }

        $url = $this->signature->notificationUrl() === null ? $request->fullUrl() : null;

        if (! $this->signature->verify($request->getContent(), $header, $url)) {
            throw InvalidSignatureException::invalid();
        }

        return $next($request);
    }
}
