<?php

namespace FLAIRUK\Square;

use BadMethodCallException;
use FLAIRUK\Square\Support\IdempotencyKey;
use FLAIRUK\Square\Support\Money;
use FLAIRUK\Square\Webhooks\WebhookSignature;
use Square\SquareClient;
use Square\Types\Money as SquareMoney;

/**
 * The configured Square SDK client plus Laravel helpers.
 *
 * Every API client on \Square\SquareClient is available as a method:
 * Square::payments() returns $client->payments, Square::customers() returns
 * $client->customers, and so on. The SDK's OAuth client is Square::client()->oAuth,
 * because Square::oauth() is the package's OAuth helper.
 *
 * @mixin SquareClient
 */
class Square
{
    protected ?SquareClient $client = null;

    /**
     * @param  array<string, mixed>  $config  the "square" config array
     */
    public function __construct(
        protected readonly ClientFactory $factory,
        protected readonly array $config,
        protected readonly ?string $accessToken = null,
    ) {}

    /**
     * The Square SDK client, created on first use.
     */
    public function client(): SquareClient
    {
        return $this->client ??= $this->factory->make($this->accessToken);
    }

    /**
     * A copy that acts for another seller, with the access token from OAuth.
     */
    public function forMerchant(string $accessToken): static
    {
        return new static($this->factory, $this->config, $accessToken);
    }

    public function environment(): Environment
    {
        return $this->factory->environment();
    }

    /**
     * SQUARE_LOCATION_ID.
     */
    public function locationId(): ?string
    {
        return filled($this->config['location_id'] ?? null) ? (string) $this->config['location_id'] : null;
    }

    /**
     * SQUARE_APPLICATION_ID.
     */
    public function applicationId(): ?string
    {
        return filled($this->config['application_id'] ?? null) ? (string) $this->config['application_id'] : null;
    }

    /**
     * SQUARE_CURRENCY (default USD).
     */
    public function currency(): string
    {
        return strtoupper((string) ($this->config['currency'] ?? 'USD'));
    }

    /**
     * A Square Money object from a decimal amount: money('12.50') is 1250 in the default currency.
     */
    public function money(int|float|string $amount, ?string $currency = null): SquareMoney
    {
        return Money::make($amount, $currency ?? $this->currency());
    }

    /**
     * A random idempotency key, or a deterministic one derived from the given parts.
     */
    public function idempotencyKey(string|int ...$parts): string
    {
        return $parts === [] ? IdempotencyKey::generate() : IdempotencyKey::for(...$parts);
    }

    /**
     * The OAuth helper, for apps that act for other sellers.
     */
    public function oauth(): OAuth
    {
        return new OAuth($this->factory, $this->config);
    }

    /**
     * Sign and verify webhook notifications with SQUARE_WEBHOOK_SIGNATURE_KEY and SQUARE_WEBHOOK_URL.
     */
    public function webhookSignature(): WebhookSignature
    {
        return new WebhookSignature(
            $this->config['webhooks']['signature_key'] ?? null,
            $this->config['webhooks']['url'] ?? null,
        );
    }

    /**
     * Square::payments(), Square::customers(), ...: the SDK's API clients.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $client = $this->client();

        if ($arguments === [] && array_key_exists($method, get_object_vars($client))) {
            return $client->{$method};
        }

        throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }
}
