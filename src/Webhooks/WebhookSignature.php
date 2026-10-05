<?php

namespace FLAIRUK\Square\Webhooks;

use FLAIRUK\Square\Exceptions\ConfigurationException;

/**
 * Square's webhook signature: base64(HMAC-SHA256(signature key, notification URL . raw body)),
 * sent in the x-square-hmacsha256-signature header.
 *
 * @see https://developer.squareup.com/docs/webhooks/step3validate
 */
final readonly class WebhookSignature
{
    public const HEADER = 'x-square-hmacsha256-signature';

    public function __construct(
        private ?string $signatureKey = null,
        private ?string $notificationUrl = null,
    ) {}

    /**
     * SQUARE_WEBHOOK_URL, if it is set.
     */
    public function notificationUrl(): ?string
    {
        return filled($this->notificationUrl) ? $this->notificationUrl : null;
    }

    /**
     * The signature Square would send for this body.
     *
     * @param  string|null  $url  the subscription's notification URL; defaults to SQUARE_WEBHOOK_URL
     */
    public function sign(string $body, ?string $url = null): string
    {
        return base64_encode(hash_hmac('sha256', $this->url($url).$body, $this->key(), true));
    }

    /**
     * Whether the signature is valid for the body, compared in constant time.
     *
     * @param  string|null  $url  the subscription's notification URL; defaults to SQUARE_WEBHOOK_URL
     *
     * @throws ConfigurationException if no signature key or notification URL is configured
     */
    public function verify(string $body, ?string $signature, ?string $url = null): bool
    {
        $expected = $this->sign($body, $url);

        if ($body === '' || blank($signature)) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    private function key(): string
    {
        return filled($this->signatureKey)
            ? $this->signatureKey
            : throw new ConfigurationException('No Square webhook signature key: set SQUARE_WEBHOOK_SIGNATURE_KEY.');
    }

    private function url(?string $url): string
    {
        $url ??= $this->notificationUrl;

        return filled($url)
            ? $url
            : throw new ConfigurationException('No Square webhook notification URL: set SQUARE_WEBHOOK_URL.');
    }
}
